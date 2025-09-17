/**
 * Laravel Notify WebSocket Client
 * 
 * A standalone JavaScript client for connecting to the Laravel Notify WebSocket server.
 * This client can be used independently of the Blade component for more advanced use cases.
 */

class LaravelNotifyClient {
    constructor(options = {}) {
        this.wsUrl = options.wsUrl || this.getDefaultUrl();
        this.token = options.token || null;
        this.userId = options.userId || null;
        this.autoReconnect = options.autoReconnect !== false;
        this.reconnectInterval = options.reconnectInterval || 3000;
        this.maxReconnectAttempts = options.maxReconnectAttempts || 10;
        
        this.ws = null;
        this.authenticated = false;
        this.subscriptions = new Set();
        this.reconnectAttempts = 0;
        this.eventHandlers = new Map();
        
        // Bind methods
        this.connect = this.connect.bind(this);
        this.disconnect = this.disconnect.bind(this);
        this.authenticate = this.authenticate.bind(this);
        this.subscribe = this.subscribe.bind(this);
        this.unsubscribe = this.unsubscribe.bind(this);
        this.send = this.send.bind(this);
        this.on = this.on.bind(this);
        this.off = this.off.bind(this);
        this.emit = this.emit.bind(this);
    }
    
    /**
     * Get default WebSocket URL
     */
    getDefaultUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = window.location.port ? `:${window.location.port}` : '';
        return `${protocol}//${host}${port}:8080`;
    }
    
    /**
     * Connect to WebSocket server
     */
    connect() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.wsUrl);
                
                this.ws.onopen = () => {
                    console.log('[LaravelNotify] WebSocket connected');
                    this.reconnectAttempts = 0;
                    this.emit('connected');
                    
                    if (this.token) {
                        this.authenticate().then(resolve).catch(reject);
                    } else {
                        resolve();
                    }
                };
                
                this.ws.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        this.handleMessage(data);
                    } catch (error) {
                        console.error('[LaravelNotify] Error parsing message:', error);
                    }
                };
                
                this.ws.onclose = (event) => {
                    console.log('[LaravelNotify] WebSocket disconnected', event.code, event.reason);
                    this.authenticated = false;
                    this.emit('disconnected', { code: event.code, reason: event.reason });
                    
                    if (this.autoReconnect && this.reconnectAttempts < this.maxReconnectAttempts) {
                        this.reconnectAttempts++;
                        console.log(`[LaravelNotify] Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
                        setTimeout(this.connect, this.reconnectInterval);
                    }
                };
                
                this.ws.onerror = (error) => {
                    console.error('[LaravelNotify] WebSocket error:', error);
                    this.emit('error', error);
                    reject(error);
                };
                
            } catch (error) {
                console.error('[LaravelNotify] Failed to connect:', error);
                reject(error);
            }
        });
    }
    
    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        this.autoReconnect = false;
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.authenticated = false;
        this.subscriptions.clear();
    }
    
    /**
     * Authenticate with the server
     */
    authenticate(token = null) {
        if (token) {
            this.token = token;
        }
        
        if (!this.token) {
            return Promise.reject(new Error('No authentication token provided'));
        }
        
        return new Promise((resolve, reject) => {
            const authHandler = (data) => {
                if (data.type === 'auth') {
                    this.off('message', authHandler);
                    if (data.data.status === 'authenticated') {
                        this.authenticated = true;
                        this.userId = data.data.user.id;
                        this.emit('authenticated', data.data.user);
                        resolve(data.data.user);
                    } else {
                        reject(new Error('Authentication failed'));
                    }
                }
            };
            
            this.on('message', authHandler);
            
            this.send({
                type: 'auth',
                token: this.token
            });
            
            // Timeout after 10 seconds
            setTimeout(() => {
                this.off('message', authHandler);
                reject(new Error('Authentication timeout'));
            }, 10000);
        });
    }
    
    /**
     * Subscribe to a channel
     */
    subscribe(channel) {
        if (this.subscriptions.has(channel)) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const subscribeHandler = (data) => {
                if (data.type === 'subscribe' && data.data.channel === channel) {
                    this.off('message', subscribeHandler);
                    if (data.data.status === 'subscribed') {
                        this.subscriptions.add(channel);
                        this.emit('subscribed', channel);
                        resolve();
                    } else {
                        reject(new Error(`Failed to subscribe to ${channel}`));
                    }
                } else if (data.type === 'error') {
                    this.off('message', subscribeHandler);
                    reject(new Error(data.data.message));
                }
            };
            
            this.on('message', subscribeHandler);
            
            this.send({
                type: 'subscribe',
                channel: channel
            });
            
            // Timeout after 5 seconds
            setTimeout(() => {
                this.off('message', subscribeHandler);
                reject(new Error(`Subscribe timeout for channel ${channel}`));
            }, 5000);
        });
    }
    
    /**
     * Unsubscribe from a channel
     */
    unsubscribe(channel) {
        if (!this.subscriptions.has(channel)) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const unsubscribeHandler = (data) => {
                if (data.type === 'unsubscribe' && data.data.channel === channel) {
                    this.off('message', unsubscribeHandler);
                    this.subscriptions.delete(channel);
                    this.emit('unsubscribed', channel);
                    resolve();
                }
            };
            
            this.on('message', unsubscribeHandler);
            
            this.send({
                type: 'unsubscribe',
                channel: channel
            });
            
            // Timeout after 5 seconds
            setTimeout(() => {
                this.off('message', unsubscribeHandler);
                resolve(); // Don't reject on timeout for unsubscribe
            }, 5000);
        });
    }
    
    /**
     * Send a message to a channel
     */
    broadcast(channel, message) {
        return this.send({
            type: 'message',
            channel: channel,
            message: message
        });
    }
    
    /**
     * Send raw message to server
     */
    send(data) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            throw new Error('WebSocket is not connected');
        }
        
        this.ws.send(JSON.stringify(data));
        return true;
    }
    
    /**
     * Handle incoming messages
     */
    handleMessage(data) {
        this.emit('message', data);
        
        switch (data.type) {
            case 'broadcast':
                this.emit('broadcast', data.data);
                this.emit(`broadcast:${data.data.channel}`, data.data.message);
                break;
                
            case 'notification':
                this.emit('notification', data.data);
                break;
                
            case 'error':
                this.emit('serverError', data.data);
                console.error('[LaravelNotify] Server error:', data.data.message);
                break;
        }
    }
    
    /**
     * Add event listener
     */
    on(event, handler) {
        if (!this.eventHandlers.has(event)) {
            this.eventHandlers.set(event, new Set());
        }
        this.eventHandlers.get(event).add(handler);
        return this;
    }
    
    /**
     * Remove event listener
     */
    off(event, handler = null) {
        if (!this.eventHandlers.has(event)) {
            return this;
        }
        
        if (handler) {
            this.eventHandlers.get(event).delete(handler);
        } else {
            this.eventHandlers.delete(event);
        }
        
        return this;
    }
    
    /**
     * Emit event to all listeners
     */
    emit(event, data = null) {
        if (!this.eventHandlers.has(event)) {
            return this;
        }
        
        this.eventHandlers.get(event).forEach(handler => {
            try {
                handler(data);
            } catch (error) {
                console.error('[LaravelNotify] Error in event handler:', error);
            }
        });
        
        return this;
    }
    
    /**
     * Get connection status
     */
    isConnected() {
        return this.ws && this.ws.readyState === WebSocket.OPEN;
    }
    
    /**
     * Get authentication status
     */
    isAuthenticated() {
        return this.authenticated;
    }
    
    /**
     * Get list of subscribed channels
     */
    getSubscriptions() {
        return Array.from(this.subscriptions);
    }
}

// Export for different module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LaravelNotifyClient;
} else if (typeof define === 'function' && define.amd) {
    define([], function() {
        return LaravelNotifyClient;
    });
} else {
    window.LaravelNotifyClient = LaravelNotifyClient;
}

// Usage examples:
/*

// Basic connection
const client = new LaravelNotifyClient({
    wsUrl: 'ws://localhost:8080',
    token: 'your-jwt-token',
    userId: 1
});

// Connect and authenticate
client.connect().then(() => {
    console.log('Connected and authenticated!');
    
    // Subscribe to channels
    client.subscribe('notifications');
    client.subscribe('user.1');
    
    // Listen for broadcasts
    client.on('broadcast:notifications', (message) => {
        console.log('Notification:', message);
    });
    
    // Listen for private notifications
    client.on('notification', (data) => {
        console.log('Private notification:', data);
    });
    
}).catch(error => {
    console.error('Connection failed:', error);
});

// Send a message to a channel
client.broadcast('chat.room1', {
    type: 'message',
    content: 'Hello everyone!',
    user: { id: 1, name: 'John' }
});

*/