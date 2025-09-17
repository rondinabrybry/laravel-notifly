# Laravel Notify

A Laravel package that enables real-time notifications, events, and chat functionality using a self-hosted WebSocket server. No external services like Pusher required!

## Features

- 🚀 **Self-hosted WebSocket server** using PHP Ratchet
- 🔐 **JWT Authentication** support
- 📡 **Laravel Broadcasting** integration
- 🎯 **Private & Public channels**
- 🔄 **Auto-reconnection** with exponential backoff
- 📱 **Responsive Blade components**
- ⚡ **Standalone JavaScript client**
- 🛡️ **CORS support**
- 📊 **Connection statistics**
- 🎨 **Easy to extend** for chat, dashboards, and alerts

## Requirements

- PHP 8.1+
- Laravel 9.0+
- ext-sockets (for WebSocket server)
- ext-pcntl (optional, for daemon mode)

## Installation

### 1. Install the package

```bash
composer require your-vendor/laravel-notify
```

### 2. Publish configuration

```bash
php artisan vendor:publish --provider="LaravelNotify\LaravelNotifyServiceProvider" --tag="config"
```

### 3. Publish assets (optional)

```bash
# Publish JavaScript client
php artisan vendor:publish --provider="LaravelNotify\LaravelNotifyServiceProvider" --tag="js"

# Publish Blade views
php artisan vendor:publish --provider="LaravelNotify\LaravelNotifyServiceProvider" --tag="views"
```

### 4. Configure broadcasting

Add the WebSocket broadcaster to your `config/broadcasting.php`:

```php
'connections' => [
    'websocket' => [
        'driver' => 'websocket',
    ],
    // ... other connections
],
```

Set your default broadcast driver in `.env`:

```env
BROADCAST_DRIVER=websocket
```

### 5. Configure WebSocket server

Update your `.env` file:

```env
# WebSocket Server Configuration
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080
WEBSOCKET_AUTH_ENABLED=true
WEBSOCKET_SECRET=your-app-key

# SSL Configuration (optional)
WEBSOCKET_SSL_ENABLED=false
WEBSOCKET_SSL_CERT=/path/to/cert.pem
WEBSOCKET_SSL_KEY=/path/to/key.pem

# Logging
WEBSOCKET_LOGGING=true
WEBSOCKET_LOG_LEVEL=info
```

## Usage

### Starting the WebSocket Server

```bash
# Start in foreground
php artisan websocket:start

# Start in daemon mode (Linux/Mac only)
php artisan websocket:start --daemon
```

### Broadcasting Events

Create a broadcastable event:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->order->user_id),
            new Channel('orders')
        ];
    }

    public function broadcastAs()
    {
        return 'order.status.updated';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'message' => "Your order #{$this->order->id} status has been updated to {$this->order->status}"
        ];
    }
}
```

Broadcast the event:

```php
// Broadcast to all subscribers
broadcast(new OrderStatusUpdated($order));

// Broadcast to others (exclude current user)
broadcast(new OrderStatusUpdated($order))->toOthers();
```

### Using the Blade Component

Add the component to your Blade template:

```html
<!DOCTYPE html>
<html>
<head>
    <meta name="websocket-token" content="{{ $websocketToken }}">
    <title>My App</title>
</head>
<body>
    <!-- Include the realtime notifications component -->
    <x-laravel-notify::realtime-notifications 
        :user-id="auth()->id()"
        :channels="['notifications', 'orders']"
    />

    <!-- Your content -->
    <div id="app">
        <!-- Your application content -->
    </div>
</body>
</html>
```

### Generating Authentication Tokens

Create a controller method to generate WebSocket authentication tokens:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LaravelNotify\Server\AuthenticationHandler;

class WebSocketController extends Controller
{
    public function getToken(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $authHandler = new AuthenticationHandler(config('realtime.auth'));
        
        $token = $authHandler->generateToken([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles ?? [],
        ]);

        return response()->json(['token' => $token]);
    }
}
```

Add a route:

```php
Route::middleware('auth')->get('/websocket/token', [WebSocketController::class, 'getToken']);
```

### Using the JavaScript Client

```html
<script src="/vendor/laravel-notify/js/laravel-notify-client.js"></script>
<script>
// Initialize the client
const client = new LaravelNotifyClient({
    wsUrl: 'ws://localhost:8080',
    token: 'your-jwt-token', // Get this from your backend
    userId: {{ auth()->id() }}
});

// Connect and authenticate
client.connect().then(() => {
    console.log('Connected!');
    
    // Subscribe to channels
    client.subscribe('notifications');
    client.subscribe('user.{{ auth()->id() }}');
    
    // Listen for specific events
    client.on('broadcast:notifications', (message) => {
        console.log('Public notification:', message);
        showNotification(message);
    });
    
    client.on('notification', (data) => {
        console.log('Private notification:', data);
        showNotification(data.notification);
    });
    
}).catch(error => {
    console.error('Failed to connect:', error);
});

function showNotification(notification) {
    // Your notification display logic
    alert(notification.message || notification.content);
}
</script>
```

### Creating Custom Events

```php
<?php

namespace App\Events;

use LaravelNotify\Events\NotificationEvent;

class NewMessageReceived extends NotificationEvent
{
    public function __construct($message, $recipientId)
    {
        $notification = [
            'type' => 'message',
            'title' => 'New Message',
            'message' => "You have a new message: {$message->content}",
            'data' => [
                'message_id' => $message->id,
                'sender' => $message->sender->name,
            ]
        ];

        parent::__construct($notification, $recipientId);
    }
}
```

## Advanced Usage

### Chat Implementation

```php
// Create a chat event
class ChatMessage implements ShouldBroadcast
{
    public $message;
    public $user;
    public $roomId;

    public function __construct($message, $user, $roomId)
    {
        $this->message = $message;
        $this->user = $user;
        $this->roomId = $roomId;
    }

    public function broadcastOn()
    {
        return [
            new PresenceChannel('chat.' . $this->roomId)
        ];
    }

    public function broadcastAs()
    {
        return 'chat.message';
    }
}
```

```javascript
// Subscribe to chat room
client.subscribe('chat.room-1');

// Listen for chat messages
client.on('broadcast:chat.room-1', (data) => {
    if (data.event === 'chat.message') {
        displayChatMessage(data.data);
    }
});

// Send a chat message
client.broadcast('chat.room-1', {
    type: 'message',
    content: 'Hello everyone!',
    user: { id: 1, name: 'John' }
});
```

### Dashboard Notifications

```php
class SystemAlert implements ShouldBroadcast
{
    public $alert;

    public function __construct($type, $message, $level = 'info')
    {
        $this->alert = [
            'type' => $type,
            'message' => $message,
            'level' => $level,
            'timestamp' => now()
        ];
    }

    public function broadcastOn()
    {
        return [
            new Channel('admin.alerts')
        ];
    }
}
```

### Custom Authentication

Implement custom authentication logic in `AuthenticationHandler`:

```php
<?php

namespace App\WebSocket;

use LaravelNotify\Server\AuthenticationHandler as BaseHandler;

class CustomAuthenticationHandler extends BaseHandler
{
    public function authenticate(string $token): ?array
    {
        // Your custom authentication logic
        $user = $this->validateCustomToken($token);
        
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $user->permissions,
        ];
    }

    public function canAccessChannel(array $user, string $channel): bool
    {
        // Your custom authorization logic
        if (str_starts_with($channel, 'admin.')) {
            return in_array('admin', $user['permissions'] ?? []);
        }

        return parent::canAccessChannel($user, $channel);
    }
}
```

## Configuration

The configuration file `config/realtime.php` contains all the settings:

```php
return [
    'server' => [
        'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
        'port' => env('WEBSOCKET_PORT', 8080),
        'timeout' => env('WEBSOCKET_TIMEOUT', 60),
        'max_connections' => env('WEBSOCKET_MAX_CONNECTIONS', 1000),
    ],

    'auth' => [
        'enabled' => env('WEBSOCKET_AUTH_ENABLED', true),
        'secret' => env('WEBSOCKET_SECRET', env('APP_KEY')),
        'token_expiry' => env('WEBSOCKET_TOKEN_EXPIRY', 3600),
    ],

    'broadcasting' => [
        'default_channel' => 'notifications',
        'allowed_channels' => [
            'notifications',
            'chat',
            'alerts',
            'updates',
        ],
        'private_channels' => [
            'user.*',
            'chat.*',
        ],
    ],
    // ... more options
];
```

## API Reference

### JavaScript Client Methods

```javascript
// Connection
client.connect()                    // Returns Promise
client.disconnect()                 // Close connection
client.isConnected()               // Returns boolean

// Authentication  
client.authenticate(token)         // Returns Promise
client.isAuthenticated()          // Returns boolean

// Channels
client.subscribe(channel)          // Returns Promise
client.unsubscribe(channel)       // Returns Promise  
client.getSubscriptions()         // Returns array

// Messaging
client.broadcast(channel, message) // Send message to channel
client.send(data)                 // Send raw data

// Events
client.on(event, handler)         // Add event listener
client.off(event, handler)        // Remove event listener
```

### Events

```javascript
// Connection events
client.on('connected', () => {})
client.on('disconnected', (data) => {})
client.on('error', (error) => {})

// Authentication events  
client.on('authenticated', (user) => {})

// Channel events
client.on('subscribed', (channel) => {})
client.on('unsubscribed', (channel) => {})

// Message events
client.on('broadcast', (data) => {})
client.on('broadcast:channel-name', (message) => {})
client.on('notification', (data) => {})
```

## Testing

```bash
# Run package tests
vendor/bin/phpunit packages/laravel-notify/tests

# Test WebSocket connection
node packages/laravel-notify/tests/js/test-client.js
```

## Security Considerations

1. **Authentication**: Always use JWT tokens for authentication
2. **Channel Authorization**: Implement proper channel access control
3. **Rate Limiting**: Consider implementing rate limiting for messages
4. **SSL**: Use WSS (WebSocket Secure) in production
5. **CORS**: Configure allowed origins properly

## Troubleshooting

### Common Issues

1. **Connection Refused**
   ```bash
   # Check if server is running
   netstat -an | grep 8080
   
   # Check firewall settings
   sudo ufw status
   ```

2. **Authentication Failed**
   - Verify JWT token is valid
   - Check token expiry
   - Ensure secret key matches

3. **Can't Subscribe to Private Channels**
   - Ensure user is authenticated
   - Check channel authorization logic

### Debugging

Enable debug mode:

```env
WEBSOCKET_LOGGING=true
WEBSOCKET_LOG_LEVEL=debug
```

Check logs:
```bash
tail -f storage/logs/websocket.log
```

## Performance Tips

1. **Use message queues** for heavy broadcasting
2. **Implement connection pooling** for high-traffic applications
3. **Use Redis** for session storage in multi-server setups
4. **Monitor memory usage** of the WebSocket server
5. **Implement message batching** for frequent updates

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Support

- 📧 Email: support@example.com
- 🐛 Issues: [GitHub Issues](https://github.com/your-username/laravel-notify/issues)
- 📖 Documentation: [Full Documentation](https://your-docs-site.com)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

Made with ❤️ for the Laravel community