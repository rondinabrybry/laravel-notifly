<div class="realtime-notifications" data-user-id="{{ $userId }}" data-channels="{{ implode(',', $channels) }}" data-ws-url="{{ $wsUrl }}">
    <div id="notification-container" class="hidden">
        <div id="notification-list"></div>
    </div>
</div>

<script>
class RealtimeNotifications {
    constructor(container) {
        this.container = container;
        this.userId = container.dataset.userId;
        this.channels = container.dataset.channels.split(',');
        this.wsUrl = container.dataset.wsUrl;
        this.ws = null;
        this.authenticated = false;
        this.subscriptions = [];
        
        this.init();
    }
    
    init() {
        this.connect();
        this.setupEventListeners();
    }
    
    connect() {
        try {
            this.ws = new WebSocket(this.wsUrl);
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.authenticate();
            };
            
            this.ws.onmessage = (event) => {
                this.handleMessage(JSON.parse(event.data));
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.authenticated = false;
                // Attempt to reconnect after 3 seconds
                setTimeout(() => this.connect(), 3000);
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };
        } catch (error) {
            console.error('Failed to connect to WebSocket:', error);
        }
    }
    
    authenticate() {
        if (!this.userId) return;
        
        // Get auth token from meta tag or localStorage
        const token = document.querySelector('meta[name="websocket-token"]')?.getAttribute('content') ||
                     localStorage.getItem('websocket_token') ||
                     this.generateGuestToken();
        
        this.send({
            type: 'auth',
            token: token
        });
    }
    
    generateGuestToken() {
        // For development - in production you should get this from your Laravel backend
        return btoa(JSON.stringify({
            user_id: this.userId,
            name: 'User ' + this.userId,
            email: 'user' + this.userId + '@example.com',
            iat: Math.floor(Date.now() / 1000),
            exp: Math.floor(Date.now() / 1000) + 3600
        }));
    }
    
    handleMessage(data) {
        switch (data.type) {
            case 'auth':
                if (data.data.status === 'authenticated') {
                    console.log('Authenticated as:', data.data.user);
                    this.authenticated = true;
                    this.subscribeToChannels();
                }
                break;
                
            case 'subscribe':
                console.log('Subscribed to channel:', data.data.channel);
                break;
                
            case 'broadcast':
                this.handleBroadcast(data.data);
                break;
                
            case 'notification':
                this.handleNotification(data.data);
                break;
                
            case 'error':
                console.error('WebSocket error:', data.data.message);
                break;
        }
    }
    
    subscribeToChannels() {
        this.channels.forEach(channel => {
            this.subscribe(channel);
        });
        
        // Subscribe to user-specific channel if authenticated
        if (this.authenticated && this.userId) {
            this.subscribe(`user.${this.userId}`);
        }
    }
    
    subscribe(channel) {
        if (this.subscriptions.includes(channel)) return;
        
        this.send({
            type: 'subscribe',
            channel: channel
        });
        
        this.subscriptions.push(channel);
    }
    
    unsubscribe(channel) {
        this.send({
            type: 'unsubscribe',
            channel: channel
        });
        
        this.subscriptions = this.subscriptions.filter(c => c !== channel);
    }
    
    handleBroadcast(data) {
        console.log('Broadcast received:', data);
        
        // Dispatch custom event
        const event = new CustomEvent('websocket:broadcast', {
            detail: data
        });
        document.dispatchEvent(event);
        
        // Show notification if it's a notification type
        if (data.message && data.message.type === 'notification') {
            this.showNotification(data.message);
        }
    }
    
    handleNotification(data) {
        console.log('Notification received:', data);
        this.showNotification(data.notification);
        
        // Dispatch custom event
        const event = new CustomEvent('websocket:notification', {
            detail: data
        });
        document.dispatchEvent(event);
    }
    
    showNotification(notification) {
        const container = this.container.querySelector('#notification-container');
        const list = this.container.querySelector('#notification-list');
        
        const notificationEl = document.createElement('div');
        notificationEl.className = 'notification-item p-4 mb-2 bg-blue-100 border border-blue-300 rounded';
        notificationEl.innerHTML = `
            <div class="notification-content">
                <h4 class="font-bold">${notification.title || 'Notification'}</h4>
                <p>${notification.message || notification.content || JSON.stringify(notification)}</p>
                <small class="text-gray-500">${new Date().toLocaleString()}</small>
            </div>
            <button class="notification-close ml-2 text-red-500 hover:text-red-700" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        list.appendChild(notificationEl);
        container.classList.remove('hidden');
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notificationEl.parentElement) {
                notificationEl.remove();
                if (list.children.length === 0) {
                    container.classList.add('hidden');
                }
            }
        }, 5000);
    }
    
    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }
    
    setupEventListeners() {
        // Listen for custom events to send messages
        document.addEventListener('websocket:send', (event) => {
            this.send(event.detail);
        });
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (this.ws) {
                this.ws.close();
            }
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const containers = document.querySelectorAll('.realtime-notifications');
    containers.forEach(container => {
        new RealtimeNotifications(container);
    });
});

// Export for manual initialization
window.RealtimeNotifications = RealtimeNotifications;
</script>

<style>
.realtime-notifications {
    position: relative;
}

#notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    animation: slideIn 0.3s ease-in-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-close {
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    border: none;
    background: none;
    padding: 0;
    margin-left: 10px;
}
</style>