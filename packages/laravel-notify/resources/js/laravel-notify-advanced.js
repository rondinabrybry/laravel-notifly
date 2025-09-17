/**
 * Laravel Notify Advanced Client
 * Enhanced WebSocket client with advanced features, themes, and mobile support
 */
class LaravelNotifyAdvanced {
    constructor(config = {}) {
        this.config = {
            websocket_url: 'ws://localhost:8080',
            authentication_token: null,
            channels: [],
            reconnect_attempts: 5,
            reconnect_delay: 3000,
            heartbeat_interval: 30000,
            enable_debug: false,
            theme: 'default',
            showAvatar: true,
            enableSound: true,
            position: 'top-right',
            maxNotifications: 5,
            autoClose: true,
            autoCloseDelay: 5000,
            enablePersistence: false,
            themeClasses: '',
            animationConfig: {},
            soundConfig: {},
            ...config
        };

        this.ws = null;
        this.reconnectCount = 0;
        this.heartbeatTimer = null;
        this.notifications = [];
        this.notificationHistory = [];
        this.isConnected = false;
        this.isReconnecting = false;
        this.connectionId = null;

        // Initialize components
        this.initializeContainer();
        this.initializeAudio();
        this.initializeServiceWorker();
        
        // Load persisted notifications
        if (this.config.enablePersistence) {
            this.loadPersistedNotifications();
        }

        // Bind methods
        this.connect = this.connect.bind(this);
        this.disconnect = this.disconnect.bind(this);
        this.reconnect = this.reconnect.bind(this);
        this.sendMessage = this.sendMessage.bind(this);
        this.showNotification = this.showNotification.bind(this);
        this.closeNotification = this.closeNotification.bind(this);
    }

    /**
     * Initialize notification container
     */
    initializeContainer() {
        this.container = document.getElementById('laravel-notify-container');
        if (!this.container) {
            this.log('error', 'Notification container not found');
            return;
        }

        // Set up container styling based on theme
        this.container.className = `fixed z-50 pointer-events-none ${this.config.position} space-y-4`;
        
        // Create status indicator
        this.createConnectionStatus();
    }

    /**
     * Initialize audio system
     */
    initializeAudio() {
        if (!this.config.enableSound) return;

        this.audioContext = null;
        this.sounds = {};

        // Initialize Web Audio API
        if (window.AudioContext || window.webkitAudioContext) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }

        // Pre-load sounds
        Object.entries(this.config.soundConfig || {}).forEach(([key, url]) => {
            this.loadSound(key, url);
        });
    }

    /**
     * Initialize Service Worker for background notifications
     */
    async initializeServiceWorker() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/vendor/laravel-notify/sw.js');
                this.log('info', 'Service Worker registered', registration);
            } catch (error) {
                this.log('warn', 'Service Worker registration failed', error);
            }
        }
    }

    /**
     * Connect to WebSocket server
     */
    connect() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.log('warn', 'Already connected');
            return;
        }

        this.log('info', 'Connecting to WebSocket server...');

        try {
            // Construct WebSocket URL with authentication
            let wsUrl = this.config.websocket_url;
            if (this.config.authentication_token) {
                wsUrl += `?token=${encodeURIComponent(this.config.authentication_token)}`;
            }

            this.ws = new WebSocket(wsUrl);
            this.setupEventListeners();

        } catch (error) {
            this.log('error', 'Failed to create WebSocket connection', error);
            this.scheduleReconnect();
        }
    }

    /**
     * Set up WebSocket event listeners
     */
    setupEventListeners() {
        this.ws.onopen = (event) => {
            this.log('info', 'Connected to WebSocket server');
            this.isConnected = true;
            this.isReconnecting = false;
            this.reconnectCount = 0;
            this.updateConnectionStatus('connected');

            // Authenticate and subscribe to channels
            this.authenticate();
            this.subscribeToChannels();

            // Start heartbeat
            this.startHeartbeat();

            // Trigger connected event
            this.dispatchEvent('connected', { event });
        };

        this.ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (error) {
                this.log('error', 'Failed to parse message', error, event.data);
            }
        };

        this.ws.onclose = (event) => {
            this.log('info', 'WebSocket connection closed', event);
            this.isConnected = false;
            this.updateConnectionStatus('disconnected');
            this.stopHeartbeat();

            // Attempt reconnection if not intentional
            if (!event.wasClean && this.reconnectCount < this.config.reconnect_attempts) {
                this.scheduleReconnect();
            }

            // Trigger disconnected event
            this.dispatchEvent('disconnected', { event });
        };

        this.ws.onerror = (error) => {
            this.log('error', 'WebSocket error', error);
            this.updateConnectionStatus('error');
            this.dispatchEvent('error', { error });
        };
    }

    /**
     * Handle incoming messages
     */
    handleMessage(data) {
        this.log('debug', 'Received message', data);

        switch (data.type) {
            case 'notification':
                this.showNotification(data);
                break;
            case 'authenticated':
                this.connectionId = data.connection_id;
                this.log('info', 'Authenticated with connection ID:', this.connectionId);
                break;
            case 'subscribed':
                this.log('info', 'Subscribed to channel:', data.channel);
                break;
            case 'unsubscribed':
                this.log('info', 'Unsubscribed from channel:', data.channel);
                break;
            case 'error':
                this.log('error', 'Server error:', data.message);
                this.showNotification({
                    type: 'error',
                    title: 'Connection Error',
                    message: data.message,
                    priority: 'high'
                });
                break;
            case 'pong':
                this.log('debug', 'Heartbeat response received');
                break;
            default:
                this.log('warn', 'Unknown message type:', data.type);
        }

        // Trigger message event
        this.dispatchEvent('message', { data });
    }

    /**
     * Show notification with advanced styling and animations
     */
    showNotification(notification) {
        // Validate notification
        if (!notification || !notification.title) {
            this.log('error', 'Invalid notification data', notification);
            return;
        }

        // Create notification object with defaults
        const notificationData = {
            id: notification.id || this.generateId(),
            type: notification.type || 'info',
            title: notification.title,
            message: notification.message || '',
            avatar: notification.avatar || null,
            timestamp: notification.timestamp || new Date().toISOString(),
            priority: notification.priority || 'normal',
            actions: notification.actions || [],
            autoClose: notification.autoClose !== undefined ? notification.autoClose : this.config.autoClose,
            duration: notification.duration || this.config.autoCloseDelay,
            persistent: notification.persistent || false,
            ...notification
        };

        // Add to notifications array
        this.notifications.push(notificationData);
        this.notificationHistory.push(notificationData);

        // Limit notifications if maximum exceeded
        if (this.notifications.length > this.config.maxNotifications) {
            const removed = this.notifications.shift();
            this.removeNotificationElement(removed.id);
        }

        // Create and show notification element
        this.createNotificationElement(notificationData);

        // Play sound
        this.playNotificationSound(notificationData.type);

        // Show browser notification if page is not visible
        if (document.hidden && 'Notification' in window) {
            this.showBrowserNotification(notificationData);
        }

        // Persist notification
        if (this.config.enablePersistence) {
            this.persistNotifications();
        }

        // Trigger notification event
        this.dispatchEvent('notification', { notification: notificationData });

        this.log('info', 'Notification displayed', notificationData);
    }

    /**
     * Create notification DOM element with theme and animations
     */
    createNotificationElement(notification) {
        // Get template based on theme
        const template = this.getNotificationTemplate();
        const element = template.cloneNode(true);

        // Apply theme classes
        const toast = element.querySelector('.notification-toast');
        if (toast) {
            toast.className += ` ${this.config.themeClasses}`;
            toast.setAttribute('data-notification-id', notification.id);
            toast.setAttribute('data-type', notification.type);
        }

        // Populate content
        this.populateNotificationContent(element, notification);

        // Apply type-specific styling
        this.applyTypeSpecificStyling(element, notification.type);

        // Add to container with animation
        this.container.appendChild(element);
        this.animateNotificationIn(element);

        // Set up auto-close
        if (notification.autoClose && !notification.persistent) {
            this.setupAutoClose(element, notification);
        }

        // Add interaction handlers
        this.setupNotificationInteractions(element, notification);
    }

    /**
     * Get notification template based on theme
     */
    getNotificationTemplate() {
        let templateId = 'notification-template-default';
        
        if (['minimal', 'material'].includes(this.config.theme)) {
            templateId = `notification-template-${this.config.theme}`;
        }

        const template = document.getElementById(templateId);
        if (!template) {
            this.log('error', `Template not found: ${templateId}`);
            return this.createFallbackTemplate();
        }

        return template.content.firstElementChild;
    }

    /**
     * Create fallback notification template
     */
    createFallbackTemplate() {
        const div = document.createElement('div');
        div.className = 'notification-toast pointer-events-auto w-full max-w-sm bg-white shadow-lg rounded-lg border border-gray-200 mb-4 p-4';
        div.innerHTML = `
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="notification-title text-sm font-semibold text-gray-900"></p>
                    <p class="notification-message text-sm text-gray-600 mt-1"></p>
                </div>
                <button type="button" class="notification-close ml-4 text-gray-400 hover:text-gray-600">
                    <span class="sr-only">Close</span>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        `;
        return div;
    }

    /**
     * Populate notification content
     */
    populateNotificationContent(element, notification) {
        // Title
        const titleElement = element.querySelector('.notification-title');
        if (titleElement) titleElement.textContent = notification.title;

        // Message
        const messageElement = element.querySelector('.notification-message');
        if (messageElement) messageElement.textContent = notification.message;

        // Timestamp
        const timeElement = element.querySelector('.notification-time');
        if (timeElement) timeElement.textContent = this.formatTimestamp(notification.timestamp);

        // Avatar
        if (this.config.showAvatar && notification.avatar) {
            const avatarImg = element.querySelector('.notification-avatar-img');
            const avatarPlaceholder = element.querySelector('.notification-avatar-placeholder');
            
            if (avatarImg && avatarPlaceholder) {
                avatarImg.src = notification.avatar;
                avatarImg.classList.remove('hidden');
                avatarPlaceholder.classList.add('hidden');
            }
        }

        // Actions
        if (notification.actions && notification.actions.length > 0) {
            this.addNotificationActions(element, notification.actions);
        }
    }

    /**
     * Add notification action buttons
     */
    addNotificationActions(element, actions) {
        const actionsContainer = element.querySelector('.notification-actions');
        if (!actionsContainer) return;

        actionsContainer.classList.remove('hidden');
        
        actions.forEach(action => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `px-3 py-1 text-xs font-medium rounded-md ${action.style || 'bg-blue-600 text-white hover:bg-blue-700'}`;
            button.textContent = action.text;
            button.onclick = () => {
                if (typeof action.handler === 'function') {
                    action.handler();
                } else if (action.url) {
                    window.open(action.url, action.target || '_blank');
                }
            };
            
            actionsContainer.querySelector('div').appendChild(button);
        });
    }

    /**
     * Apply type-specific styling (success, warning, error, etc.)
     */
    applyTypeSpecificStyling(element, type) {
        const progressBar = element.querySelector('.notification-progress');
        const iconPlaceholder = element.querySelector('.notification-icon-placeholder');
        const borderElement = element.querySelector('.notification-toast');

        const typeConfigs = {
            success: { 
                color: 'bg-green-500', 
                border: 'border-l-green-500',
                icon: this.getSuccessIcon(),
                iconBg: 'bg-green-100 text-green-600'
            },
            warning: { 
                color: 'bg-yellow-500', 
                border: 'border-l-yellow-500',
                icon: this.getWarningIcon(),
                iconBg: 'bg-yellow-100 text-yellow-600'
            },
            error: { 
                color: 'bg-red-500', 
                border: 'border-l-red-500',
                icon: this.getErrorIcon(),
                iconBg: 'bg-red-100 text-red-600'
            },
            info: { 
                color: 'bg-blue-500', 
                border: 'border-l-blue-500',
                icon: this.getInfoIcon(),
                iconBg: 'bg-blue-100 text-blue-600'
            }
        };

        const config = typeConfigs[type] || typeConfigs.info;

        if (progressBar) {
            progressBar.className = progressBar.className.replace(/bg-\w+-500/, config.color);
        }

        if (borderElement && config.border) {
            borderElement.classList.add(config.border);
        }

        if (iconPlaceholder && config.icon) {
            iconPlaceholder.innerHTML = config.icon;
            iconPlaceholder.className = `w-8 h-8 rounded-full flex items-center justify-center ${config.iconBg}`;
        }
    }

    /**
     * Animate notification entrance
     */
    animateNotificationIn(element) {
        const animConfig = this.config.animationConfig;
        
        if (animConfig.enter) {
            element.classList.add(...animConfig.enter.split(' '));
            
            // Trigger animation
            requestAnimationFrame(() => {
                element.classList.remove(...animConfig.enter.split(' '));
                if (animConfig.enterTo) {
                    element.classList.add(...animConfig.enterTo.split(' '));
                }
            });
        }
    }

    /**
     * Animate notification exit
     */
    animateNotificationOut(element, callback) {
        const animConfig = this.config.animationConfig;
        
        if (animConfig.leave && animConfig.leaveTo) {
            element.classList.add(...animConfig.leave.split(' '));
            
            requestAnimationFrame(() => {
                element.classList.add(...animConfig.leaveTo.split(' '));
                
                setTimeout(() => {
                    if (callback) callback();
                }, 200); // Match animation duration
            });
        } else {
            if (callback) callback();
        }
    }

    /**
     * Setup auto-close functionality with progress bar
     */
    setupAutoClose(element, notification) {
        const progressBar = element.querySelector('.notification-progress');
        const duration = notification.duration;
        
        let startTime = Date.now();
        let pausedTime = 0;
        let isPaused = false;
        
        const updateProgress = () => {
            if (isPaused) return;
            
            const elapsed = Date.now() - startTime - pausedTime;
            const progress = Math.max(0, 100 - (elapsed / duration) * 100);
            
            if (progressBar) {
                progressBar.style.width = progress + '%';
            }
            
            if (progress <= 0) {
                this.removeNotificationElement(notification.id);
            } else {
                requestAnimationFrame(updateProgress);
            }
        };
        
        // Pause on hover
        element.addEventListener('mouseenter', () => {
            isPaused = true;
        });
        
        element.addEventListener('mouseleave', () => {
            if (isPaused) {
                pausedTime += Date.now() - startTime;
                startTime = Date.now();
                isPaused = false;
                requestAnimationFrame(updateProgress);
            }
        });
        
        // Start progress animation
        requestAnimationFrame(updateProgress);
    }

    /**
     * Setup notification interactions
     */
    setupNotificationInteractions(element, notification) {
        // Close button
        const closeButton = element.querySelector('.notification-close');
        if (closeButton) {
            closeButton.onclick = () => this.closeNotification(element);
        }

        // Click to focus (mobile support)
        element.addEventListener('click', (e) => {
            if (e.target === element || e.target.closest('.notification-toast') === element.querySelector('.notification-toast')) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Swipe to dismiss (mobile support)
        if ('ontouchstart' in window) {
            this.setupSwipeToClose(element, notification);
        }
    }

    /**
     * Setup swipe to close for mobile
     */
    setupSwipeToClose(element, notification) {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let isDragging = false;

        element.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;
        });

        element.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            currentX = e.touches[0].clientX;
            currentY = e.touches[0].clientY;
            
            const deltaX = currentX - startX;
            const deltaY = Math.abs(currentY - startY);
            
            // Only swipe horizontally
            if (deltaY < 50) {
                element.style.transform = `translateX(${deltaX}px)`;
                element.style.opacity = Math.max(0.3, 1 - Math.abs(deltaX) / 200);
                e.preventDefault();
            }
        });

        element.addEventListener('touchend', () => {
            if (!isDragging) return;
            
            const deltaX = currentX - startX;
            
            if (Math.abs(deltaX) > 100) {
                // Swipe distance threshold reached - close notification
                this.animateNotificationOut(element, () => {
                    this.removeNotificationElement(notification.id);
                });
            } else {
                // Snap back
                element.style.transform = '';
                element.style.opacity = '';
            }
            
            isDragging = false;
        });
    }

    /**
     * Close notification
     */
    closeNotification(elementOrButton) {
        const element = elementOrButton.closest ? 
            elementOrButton.closest('.notification-toast').parentElement :
            elementOrButton;
            
        const notificationId = element.querySelector('.notification-toast').getAttribute('data-notification-id');
        this.removeNotificationElement(notificationId);
    }

    /**
     * Remove notification element
     */
    removeNotificationElement(notificationId) {
        const element = this.container.querySelector(`[data-notification-id="${notificationId}"]`)?.parentElement;
        if (!element) return;

        this.animateNotificationOut(element, () => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
        });

        // Remove from notifications array
        this.notifications = this.notifications.filter(n => n.id !== notificationId);

        // Update persistence
        if (this.config.enablePersistence) {
            this.persistNotifications();
        }
    }

    /**
     * Play notification sound
     */
    playNotificationSound(type) {
        if (!this.config.enableSound || !this.audioContext) return;

        const soundKey = this.config.soundConfig[type] ? type : 'notification';
        const sound = this.sounds[soundKey];

        if (sound) {
            try {
                const source = this.audioContext.createBufferSource();
                source.buffer = sound;
                source.connect(this.audioContext.destination);
                source.start();
            } catch (error) {
                this.log('warn', 'Failed to play notification sound', error);
            }
        }
    }

    /**
     * Load sound file
     */
    async loadSound(key, url) {
        if (!this.audioContext) return;

        try {
            const response = await fetch(url);
            const arrayBuffer = await response.arrayBuffer();
            const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
            this.sounds[key] = audioBuffer;
        } catch (error) {
            this.log('warn', `Failed to load sound: ${key}`, error);
        }
    }

    /**
     * Show browser notification
     */
    async showBrowserNotification(notification) {
        if (Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;
        }

        const browserNotification = new Notification(notification.title, {
            body: notification.message,
            icon: notification.avatar || '/vendor/laravel-notify/icon.png',
            badge: '/vendor/laravel-notify/badge.png',
            tag: notification.id,
            requireInteraction: notification.persistent
        });

        browserNotification.onclick = () => {
            window.focus();
            browserNotification.close();
        };
    }

    /**
     * Toggle notification center modal
     */
    toggleNotificationCenter() {
        const modal = document.getElementById('notification-center-modal');
        if (!modal) return;

        const isVisible = !modal.classList.contains('hidden');
        
        if (isVisible) {
            modal.classList.add('hidden');
        } else {
            this.populateNotificationCenter();
            modal.classList.remove('hidden');
        }
    }

    /**
     * Populate notification center with history
     */
    populateNotificationCenter() {
        const list = document.getElementById('notification-center-list');
        const empty = document.getElementById('notification-center-empty');
        
        if (!list || !empty) return;

        if (this.notificationHistory.length === 0) {
            list.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');
        list.classList.remove('hidden');
        list.innerHTML = '';

        // Show recent notifications (last 20)
        const recent = this.notificationHistory.slice(-20).reverse();
        
        recent.forEach(notification => {
            const item = document.createElement('div');
            item.className = 'p-3 border border-gray-200 rounded-md';
            item.innerHTML = `
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                        ${this.getTypeIcon(notification.type)}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                        <p class="text-sm text-gray-500">${notification.message}</p>
                        <p class="text-xs text-gray-400 mt-1">${this.formatTimestamp(notification.timestamp)}</p>
                    </div>
                </div>
            `;
            list.appendChild(item);
        });
    }

    /**
     * Clear all notifications
     */
    clearAllNotifications() {
        // Clear active notifications
        this.notifications.forEach(notification => {
            this.removeNotificationElement(notification.id);
        });

        // Clear history
        this.notificationHistory = [];
        
        // Update persistence
        if (this.config.enablePersistence) {
            localStorage.removeItem('laravel_notify_history');
        }

        // Close notification center
        this.toggleNotificationCenter();
    }

    /**
     * Connection status indicator
     */
    createConnectionStatus() {
        const statusIndicator = document.createElement('div');
        statusIndicator.id = 'laravel-notify-status';
        statusIndicator.className = 'fixed bottom-4 left-4 z-50 px-3 py-1 rounded-full text-xs font-medium transition-all duration-300';
        statusIndicator.style.display = this.config.enable_debug ? 'block' : 'none';
        
        document.body.appendChild(statusIndicator);
        this.statusIndicator = statusIndicator;
        this.updateConnectionStatus('disconnected');
    }

    /**
     * Update connection status
     */
    updateConnectionStatus(status) {
        if (!this.statusIndicator) return;

        const statusConfigs = {
            connecting: { text: 'Connecting...', class: 'bg-yellow-100 text-yellow-800' },
            connected: { text: 'Connected', class: 'bg-green-100 text-green-800' },
            disconnected: { text: 'Disconnected', class: 'bg-gray-100 text-gray-800' },
            error: { text: 'Connection Error', class: 'bg-red-100 text-red-800' },
            reconnecting: { text: 'Reconnecting...', class: 'bg-orange-100 text-orange-800' }
        };

        const config = statusConfigs[status] || statusConfigs.disconnected;
        
        this.statusIndicator.textContent = config.text;
        this.statusIndicator.className = `fixed bottom-4 left-4 z-50 px-3 py-1 rounded-full text-xs font-medium transition-all duration-300 ${config.class}`;
    }

    /**
     * Persist notifications to localStorage
     */
    persistNotifications() {
        if (!this.config.enablePersistence) return;

        try {
            localStorage.setItem('laravel_notify_active', JSON.stringify(this.notifications));
            localStorage.setItem('laravel_notify_history', JSON.stringify(this.notificationHistory.slice(-50))); // Keep last 50
        } catch (error) {
            this.log('warn', 'Failed to persist notifications', error);
        }
    }

    /**
     * Load persisted notifications from localStorage
     */
    loadPersistedNotifications() {
        try {
            const active = localStorage.getItem('laravel_notify_active');
            const history = localStorage.getItem('laravel_notify_history');

            if (active) {
                this.notifications = JSON.parse(active);
                // Re-create notification elements for active notifications
                this.notifications.forEach(notification => {
                    this.createNotificationElement(notification);
                });
            }

            if (history) {
                this.notificationHistory = JSON.parse(history);
            }
        } catch (error) {
            this.log('warn', 'Failed to load persisted notifications', error);
        }
    }

    /**
     * Subscribe to channels
     */
    subscribeToChannels() {
        this.config.channels.forEach(channel => {
            this.subscribe(channel);
        });
    }

    /**
     * Subscribe to a channel
     */
    subscribe(channel) {
        this.sendMessage({
            type: 'subscribe',
            channel: channel
        });
    }

    /**
     * Unsubscribe from a channel
     */
    unsubscribe(channel) {
        this.sendMessage({
            type: 'unsubscribe',
            channel: channel
        });
    }

    /**
     * Send message to WebSocket server
     */
    sendMessage(data) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            this.log('warn', 'Cannot send message: WebSocket not connected');
            return false;
        }

        try {
            this.ws.send(JSON.stringify(data));
            this.log('debug', 'Message sent', data);
            return true;
        } catch (error) {
            this.log('error', 'Failed to send message', error);
            return false;
        }
    }

    /**
     * Authenticate with server
     */
    authenticate() {
        if (this.config.authentication_token) {
            this.sendMessage({
                type: 'authenticate',
                token: this.config.authentication_token
            });
        }
    }

    /**
     * Start heartbeat mechanism
     */
    startHeartbeat() {
        this.stopHeartbeat(); // Clear any existing timer
        
        if (this.config.heartbeat_interval > 0) {
            this.heartbeatTimer = setInterval(() => {
                this.sendMessage({ type: 'ping' });
            }, this.config.heartbeat_interval);
        }
    }

    /**
     * Stop heartbeat mechanism
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect() {
        if (this.isReconnecting || this.reconnectCount >= this.config.reconnect_attempts) {
            return;
        }

        this.isReconnecting = true;
        this.reconnectCount++;
        this.updateConnectionStatus('reconnecting');

        this.log('info', `Scheduling reconnection attempt ${this.reconnectCount}/${this.config.reconnect_attempts}`);

        setTimeout(() => {
            this.reconnect();
        }, this.config.reconnect_delay * this.reconnectCount); // Exponential backoff
    }

    /**
     * Attempt to reconnect
     */
    reconnect() {
        this.log('info', `Reconnection attempt ${this.reconnectCount}/${this.config.reconnect_attempts}`);
        this.connect();
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        this.log('info', 'Disconnecting from WebSocket server');
        
        this.stopHeartbeat();
        
        if (this.ws) {
            this.ws.close(1000, 'Client disconnect');
            this.ws = null;
        }

        this.isConnected = false;
        this.isReconnecting = false;
        this.reconnectCount = 0;
        this.updateConnectionStatus('disconnected');
    }

    /**
     * Dispatch custom event
     */
    dispatchEvent(eventType, detail = {}) {
        const event = new CustomEvent(`laravel-notify-${eventType}`, {
            detail,
            bubbles: true
        });
        document.dispatchEvent(event);
    }

    /**
     * Get type-specific icons
     */
    getSuccessIcon() {
        return `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>`;
    }

    getWarningIcon() {
        return `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>`;
    }

    getErrorIcon() {
        return `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>`;
    }

    getInfoIcon() {
        return `<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>`;
    }

    getTypeIcon(type) {
        const icons = {
            success: this.getSuccessIcon(),
            warning: this.getWarningIcon(),
            error: this.getErrorIcon(),
            info: this.getInfoIcon()
        };
        return icons[type] || icons.info;
    }

    /**
     * Format timestamp for display
     */
    formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) { // Less than 1 minute
            return 'just now';
        } else if (diff < 3600000) { // Less than 1 hour
            return `${Math.floor(diff / 60000)}m ago`;
        } else if (diff < 86400000) { // Less than 1 day
            return `${Math.floor(diff / 3600000)}h ago`;
        } else {
            return date.toLocaleDateString();
        }
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return 'notification_' + Math.random().toString(36).substr(2, 9) + Date.now();
    }

    /**
     * Logging utility
     */
    log(level, message, ...args) {
        if (!this.config.enable_debug && level === 'debug') return;

        const timestamp = new Date().toISOString();
        const logMessage = `[LaravelNotify ${timestamp}] ${message}`;

        switch (level) {
            case 'error':
                console.error(logMessage, ...args);
                break;
            case 'warn':
                console.warn(logMessage, ...args);
                break;
            case 'info':
                console.info(logMessage, ...args);
                break;
            case 'debug':
                console.debug(logMessage, ...args);
                break;
            default:
                console.log(logMessage, ...args);
        }
    }

    /**
     * Get connection statistics
     */
    getStats() {
        return {
            isConnected: this.isConnected,
            reconnectCount: this.reconnectCount,
            activeNotifications: this.notifications.length,
            totalNotifications: this.notificationHistory.length,
            connectionId: this.connectionId
        };
    }

    /**
     * Enable/disable persistence
     */
    enablePersistence(enabled) {
        this.config.enablePersistence = enabled;
        
        if (enabled) {
            this.persistNotifications();
        } else {
            localStorage.removeItem('laravel_notify_active');
            localStorage.removeItem('laravel_notify_history');
        }
    }
}

// Make globally available
window.LaravelNotifyAdvanced = LaravelNotifyAdvanced;