/**
 * Frontend JavaScript Example for Chat Implementation
 */

class ChatApp {
    constructor(options = {}) {
        this.roomId = options.roomId;
        this.userId = options.userId;
        this.wsClient = null;
        this.messageContainer = document.getElementById('chat-messages');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.typingIndicator = document.getElementById('typing-indicator');
        
        this.typingUsers = new Set();
        this.typingTimeout = null;
        
        this.init();
    }
    
    async init() {
        try {
            // Get WebSocket token from server
            const tokenResponse = await fetch('/websocket/token');
            const { token } = await tokenResponse.json();
            
            // Initialize WebSocket client
            this.wsClient = new LaravelNotifyClient({
                wsUrl: 'ws://localhost:8080',
                token: token,
                userId: this.userId
            });
            
            await this.wsClient.connect();
            await this.wsClient.subscribe(`chat.room.${this.roomId}`);
            
            this.setupEventListeners();
            this.loadChatHistory();
            
        } catch (error) {
            console.error('Failed to initialize chat:', error);
            this.showError('Failed to connect to chat. Please refresh the page.');
        }
    }
    
    setupEventListeners() {
        // Listen for chat messages
        this.wsClient.on(`broadcast:chat.room.${this.roomId}`, (data) => {
            if (data.event === 'chat.message') {
                this.displayMessage(data.data.message);
            }
        });
        
        // Listen for typing indicators
        this.wsClient.on('broadcast', (data) => {
            if (data.message && data.message.type === 'typing') {
                this.handleTypingIndicator(data.message);
            }
        });
        
        // Send message on button click
        this.sendButton.addEventListener('click', () => this.sendMessage());
        
        // Send message on Enter key
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Handle typing indicators
        this.messageInput.addEventListener('input', () => {
            this.handleTyping();
        });
        
        // Stop typing when input loses focus
        this.messageInput.addEventListener('blur', () => {
            this.sendTypingIndicator(false);
        });
    }
    
    async loadChatHistory() {
        try {
            const response = await fetch(`/chat/history?room_id=${this.roomId}`);
            const { messages } = await response.json();
            
            messages.reverse().forEach(message => {
                this.displayMessage(message, false);
            });
            
            this.scrollToBottom();
        } catch (error) {
            console.error('Failed to load chat history:', error);
        }
    }
    
    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        try {
            const response = await fetch('/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    room_id: this.roomId,
                    message: message
                })
            });
            
            if (response.ok) {
                this.messageInput.value = '';
                this.sendTypingIndicator(false);
            } else {
                this.showError('Failed to send message');
            }
            
        } catch (error) {
            console.error('Failed to send message:', error);
            this.showError('Failed to send message');
        }
    }
    
    displayMessage(message, animate = true) {
        const messageElement = document.createElement('div');
        messageElement.className = `message ${message.user.id === this.userId ? 'own-message' : 'other-message'}`;
        
        if (animate) {
            messageElement.classList.add('message-animate');
        }
        
        messageElement.innerHTML = `
            <div class="message-header">
                <img src="${message.user.avatar || '/default-avatar.png'}" 
                     alt="${message.user.name}" class="user-avatar">
                <span class="user-name">${message.user.name}</span>
                <span class="message-time">${this.formatTime(message.created_at)}</span>
            </div>
            <div class="message-content">${this.escapeHtml(message.content)}</div>
        `;
        
        this.messageContainer.appendChild(messageElement);
        
        if (animate) {
            this.scrollToBottom();
        }
    }
    
    handleTyping() {
        this.sendTypingIndicator(true);
        
        // Clear existing timeout
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }
        
        // Set new timeout to stop typing indicator
        this.typingTimeout = setTimeout(() => {
            this.sendTypingIndicator(false);
        }, 2000);
    }
    
    async sendTypingIndicator(isTyping) {
        try {
            await fetch('/chat/typing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    room_id: this.roomId,
                    is_typing: isTyping
                })
            });
        } catch (error) {
            console.error('Failed to send typing indicator:', error);
        }
    }
    
    handleTypingIndicator(data) {
        if (data.user.id === this.userId) return; // Ignore own typing
        
        if (data.is_typing) {
            this.typingUsers.add(data.user.name);
        } else {
            this.typingUsers.delete(data.user.name);
        }
        
        this.updateTypingIndicator();
    }
    
    updateTypingIndicator() {
        const typingArray = Array.from(this.typingUsers);
        
        if (typingArray.length === 0) {
            this.typingIndicator.style.display = 'none';
        } else {
            this.typingIndicator.style.display = 'block';
            
            let text;
            if (typingArray.length === 1) {
                text = `${typingArray[0]} is typing...`;
            } else if (typingArray.length === 2) {
                text = `${typingArray[0]} and ${typingArray[1]} are typing...`;
            } else {
                text = `${typingArray.length} people are typing...`;
            }
            
            this.typingIndicator.textContent = text;
        }
    }
    
    scrollToBottom() {
        this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
    }
    
    formatTime(timestamp) {
        return new Date(timestamp).toLocaleTimeString([], { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showError(message) {
        const errorElement = document.createElement('div');
        errorElement.className = 'chat-error';
        errorElement.textContent = message;
        
        this.messageContainer.appendChild(errorElement);
        this.scrollToBottom();
        
        setTimeout(() => {
            errorElement.remove();
        }, 5000);
    }
}

// Usage example
document.addEventListener('DOMContentLoaded', () => {
    const chatContainer = document.getElementById('chat-container');
    if (chatContainer) {
        const roomId = chatContainer.dataset.roomId;
        const userId = chatContainer.dataset.userId;
        
        new ChatApp({ roomId, userId });
    }
});

/* CSS for chat styling */
const chatStyles = `
.chat-container {
    display: flex;
    flex-direction: column;
    height: 500px;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f9f9f9;
}

.message {
    margin-bottom: 1rem;
    padding: 0.5rem;
    border-radius: 8px;
    max-width: 70%;
}

.own-message {
    align-self: flex-end;
    background: #007bff;
    color: white;
    margin-left: auto;
}

.other-message {
    background: white;
    border: 1px solid #ddd;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.user-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
}

.user-name {
    font-weight: bold;
}

.message-time {
    color: #666;
    margin-left: auto;
}

.message-content {
    word-wrap: break-word;
}

.message-animate {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(20px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.chat-input {
    display: flex;
    padding: 1rem;
    background: white;
    border-top: 1px solid #ddd;
    gap: 0.5rem;
}

.chat-input input {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.chat-input button {
    padding: 0.5rem 1rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.typing-indicator {
    padding: 0.5rem 1rem;
    font-style: italic;
    color: #666;
    background: #f0f0f0;
    border-top: 1px solid #ddd;
}

.chat-error {
    background: #f8d7da;
    color: #721c24;
    padding: 0.5rem;
    margin: 0.5rem 0;
    border-radius: 4px;
    text-align: center;
}
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = chatStyles;
document.head.appendChild(styleSheet);