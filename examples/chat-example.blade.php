<!-- HTML Template for Chat Implementation -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Notify Chat Example</title>
    
    <!-- Include Laravel Notify Client -->
    <script src="/vendor/laravel-notify/js/laravel-notify-client.js"></script>
</head>
<body>
    <div class="container">
        <h1>Chat Room: {{ $room->name }}</h1>
        
        <!-- Chat Container -->
        <div id="chat-container" class="chat-container" 
             data-room-id="{{ $room->id }}" 
             data-user-id="{{ auth()->id() }}">
            
            <!-- Messages Display -->
            <div id="chat-messages" class="chat-messages">
                <!-- Messages will be loaded here -->
            </div>
            
            <!-- Typing Indicator -->
            <div id="typing-indicator" class="typing-indicator" style="display: none;">
                <!-- Typing indicator text will appear here -->
            </div>
            
            <!-- Message Input -->
            <div class="chat-input">
                <input type="text" 
                       id="message-input" 
                       placeholder="Type your message..." 
                       maxlength="1000">
                <button id="send-button">Send</button>
            </div>
        </div>
        
        <!-- Online Users (optional) -->
        <div class="online-users">
            <h3>Online Users</h3>
            <div id="online-users-list">
                <!-- Online users will be displayed here -->
            </div>
        </div>
        
        <!-- Notification Component -->
        <x-laravel-notify::realtime-notifications 
            :user-id="auth()->id()"
            :channels="['notifications', 'user.' . auth()->id()]"
        />
    </div>
    
    <!-- Include Chat JavaScript -->
    <script src="/vendor/laravel-notify/js/chat-example.js"></script>
    
    <script>
        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', () => {
            // Chat is auto-initialized by the chat-frontend.js script
            console.log('Chat initialized for room {{ $room->id }}');
        });
    </script>
</body>
</html>