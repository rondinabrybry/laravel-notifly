# Laravel Notify - Production-Ready WebSocket Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/your-vendor/laravel-notify.svg?style=flat-square)](https://packagist.org/packages/your-vendor/laravel-notify)
[![Total Downloads](https://img.shields.io/packagist/dt/your-vendor/laravel-notify.svg?style=flat-square)](https://packagist.org/packages/your-vendor/laravel-notify)
[![Tests](https://img.shields.io/github/actions/workflow/status/your-vendor/laravel-notify/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/your-vendor/laravel-notify/actions/workflows/tests.yml)
[![PHP Version Require](http://poser.pugx.org/your-vendor/laravel-notify/require/php)](https://packagist.org/packages/your-vendor/laravel-notify)

A comprehensive, production-ready Laravel package for real-time notifications, events, and chat functionality using a self-hosted WebSocket server. Built for scalability, security, and ease of use.

## 🚀 Features

### Core Features
- **Self-hosted WebSocket Server**: Built with PHP Ratchet for complete control
- **Real-time Notifications**: Instant delivery with advanced theming and animations
- **Multi-Authentication Support**: JWT, Laravel Sanctum, and session-based authentication
- **Channel Subscriptions**: Flexible channel-based messaging with authorization
- **Broadcasting Integration**: Seamless Laravel broadcasting system integration
- **Event-driven Architecture**: Clean, extensible event-listener pattern

### Production Features
- **Redis Clustering**: Multi-server scaling with connection state sharing
- **Rate Limiting**: IP-based connection and message throttling with whitelist/blacklist
- **Security Features**: DDoS protection, geo-blocking, IP filtering
- **Metrics & Monitoring**: Prometheus/InfluxDB integration with comprehensive metrics
- **Health Checks**: Built-in health endpoints for load balancers
- **Load Balancing**: Multi-server deployment with health monitoring

### Developer Experience
- **Comprehensive Testing**: Unit, feature, and browser tests included
- **Advanced UI Components**: Multiple themes, mobile support, notification center
- **CLI Management**: Complete Artisan command suite for server management
- **Plugin System**: Extensible architecture for third-party integrations
- **TypeScript Support**: Type definitions for frontend integration

## 📋 Requirements

- **PHP**: ^8.1
- **Laravel**: ^9.0|^10.0|^11.0
- **Extensions**: ext-redis, ext-json, ext-mbstring
- **Optional**: Redis server (for clustering), Node.js (for frontend build tools)

## 📦 Installation

Install the package via Composer:

```bash
composer require your-vendor/laravel-notify
```

Publish configuration and assets:

```bash
# Publish configuration
php artisan vendor:publish --tag=laravel-notify-config

# Publish assets (optional)
php artisan vendor:publish --tag=laravel-notify-assets
php artisan vendor:publish --tag=laravel-notify-views
```

## ⚙️ Configuration

Configure your environment variables:

```bash
# WebSocket Server Configuration
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=8080
WEBSOCKET_MAX_CONNECTIONS=1000
WEBSOCKET_TIMEOUT=60

# Authentication
WEBSOCKET_AUTH_ENABLED=true
WEBSOCKET_AUTH_PROVIDER=jwt
WEBSOCKET_SECRET=your-secret-key

# Redis Clustering (optional)
WEBSOCKET_REDIS_ENABLED=false
WEBSOCKET_REDIS_CONNECTION=default
WEBSOCKET_CLUSTER_ID=server-1

# Rate Limiting
WEBSOCKET_RATE_LIMITING_ENABLED=true
WEBSOCKET_MESSAGES_PER_MINUTE=60
WEBSOCKET_CONNECTIONS_PER_IP=10

# Security
WEBSOCKET_IP_WHITELIST=
WEBSOCKET_IP_BLACKLIST=
WEBSOCKET_DDOS_PROTECTION=true

# Metrics
WEBSOCKET_METRICS_ENABLED=false
WEBSOCKET_METRICS_DRIVER=prometheus
```

## 🔧 Usage

### Starting the WebSocket Server

```bash
# Start the server
php artisan websocket:start

# Start with specific options
php artisan websocket:start --host=127.0.0.1 --port=8080 --daemon

# Check server status
php artisan websocket:status

# Stop the server
php artisan websocket:stop

# Restart the server
php artisan websocket:restart

# View active connections
php artisan websocket:clients

# Monitor channels
php artisan websocket:channels
```

### Basic Implementation

Add real-time notifications to your Blade templates:

```blade
{{-- Basic notifications --}}
<x-laravel-notify::realtime-notifications />

{{-- Advanced notifications with custom theme --}}
<x-laravel-notify::realtime-notifications-advanced 
    :theme="'dark'"
    :position="'bottom-right'"
    :max-notifications="10"
    :enable-sound="true"
    :show-avatar="true"
    :auto-close="true"
    :auto-close-delay="7000"
    :enable-persistence="true"
/>
```

### Broadcasting Events

```php
use LaravelNotify\Events\NotificationEvent;

// Simple notification
event(new NotificationEvent([
    'type' => 'success',
    'title' => 'Welcome!',
    'message' => 'You have successfully connected.',
    'channel' => 'notifications.' . auth()->id(),
]));

// Advanced notification with actions
event(new NotificationEvent([
    'type' => 'info',
    'title' => 'New Message',
    'message' => 'You have received a new message from John Doe.',
    'channel' => 'notifications.' . auth()->id(),
    'avatar' => 'https://example.com/avatars/john.jpg',
    'actions' => [
        [
            'text' => 'View Message',
            'url' => '/messages/123',
            'style' => 'bg-blue-600 text-white hover:bg-blue-700'
        ],
        [
            'text' => 'Mark as Read',
            'handler' => 'markAsRead',
            'style' => 'bg-gray-200 text-gray-800 hover:bg-gray-300'
        ]
    ],
    'priority' => 'high',
    'persistent' => true
]));
```

### Frontend JavaScript Integration

```javascript
// Access the global LaravelNotify instance
const notify = window.LaravelNotify;

// Subscribe to channels
notify.subscribe('notifications.' + userId);
notify.subscribe('chat-room-' + roomId);

// Send messages
notify.sendMessage({
    type: 'chat',
    message: 'Hello everyone!',
    channel: 'chat-room-1',
    user: {
        id: userId,
        name: userName,
        avatar: userAvatar
    }
});

// Custom notification
notify.showNotification({
    type: 'warning',
    title: 'Connection Issue',
    message: 'Your connection seems unstable.',
    autoClose: false,
    actions: [
        {
            text: 'Retry',
            handler: () => notify.reconnect()
        }
    ]
});

// Event listeners
document.addEventListener('laravel-notify-connected', (event) => {
    console.log('WebSocket connected:', event.detail);
});

document.addEventListener('laravel-notify-notification', (event) => {
    console.log('Notification received:', event.detail.notification);
});

// Get connection statistics
console.log(notify.getStats());
```

## 🔐 Authentication

### JWT Authentication

```php
use LaravelNotify\Authentication\Providers\JWTAuthProvider;

$jwtProvider = new JWTAuthProvider([
    'secret' => config('app.key'),
    'algorithm' => 'HS256',
    'expires_in' => 3600,
]);

// Generate token for user
$token = $jwtProvider->generateToken([
    'user_id' => auth()->id(),
    'name' => auth()->user()->name,
    'email' => auth()->user()->email,
]);

// Use token in frontend
echo "<script>window.authToken = '{$token}';</script>";
```

### Laravel Sanctum Integration

```php
// Create Sanctum token for WebSocket
$token = auth()->user()->createToken('websocket', ['websocket:connect']);

// Frontend usage
echo "<script>window.authToken = '{$token->plainTextToken}';</script>";
```

### Session Authentication

```php
use LaravelNotify\Authentication\Providers\SessionAuthProvider;

$sessionProvider = new SessionAuthProvider();

// Generate session token for WebSocket
$sessionToken = $sessionProvider->generateSessionToken();

// Frontend usage
echo "<script>window.sessionToken = '{$sessionToken}';</script>";
```

## 📊 Scaling with Redis

Enable Redis for multi-server deployments:

```php
// config/realtime.php
'redis' => [
    'enabled' => true,
    'connection' => 'default',
    'cluster_id' => env('WEBSOCKET_CLUSTER_ID', gethostname()),
    'prefix' => 'laravel_notify:',
    'ttl' => 3600,
    'clustering' => [
        'enabled' => true,
        'nodes' => [
            ['host' => '127.0.0.1', 'port' => 6379],
            ['host' => '127.0.0.1', 'port' => 6380],
        ],
    ],
],
```

## 🛡️ Security Features

### Rate Limiting

```php
'rate_limiting' => [
    'enabled' => true,
    'messages_per_minute' => 60,
    'connections_per_ip' => 10,
    'burst_limit' => 10,
    'whitelist' => ['127.0.0.1', '::1'],
    'blacklist' => ['banned.ip.address'],
],
```

### IP Filtering and Geo-blocking

```php
'security' => [
    'ip_whitelist' => ['trusted.ip.range'],
    'ip_blacklist' => ['malicious.ip.range'],
    'geo_blocking' => [
        'enabled' => true,
        'allowed_countries' => ['US', 'CA', 'GB'],
        'blocked_countries' => ['XX', 'YY'],
    ],
    'ddos_protection' => [
        'enabled' => true,
        'max_requests_per_second' => 100,
        'ban_duration' => 3600,
    ],
],
```

## 📈 Monitoring and Metrics

### Health Checks

```bash
# Health check endpoint
GET /api/websocket/health

# Detailed status
GET /api/websocket/status

# Prometheus metrics
GET /api/websocket/metrics
```

### Custom Metrics

```php
use LaravelNotify\Metrics\ConnectionMetrics;
use LaravelNotify\Metrics\MessageMetrics;

// Track custom metrics
ConnectionMetrics::increment('custom_connections', ['server' => 'web-1']);
MessageMetrics::histogram('message_processing_time', 0.025, ['type' => 'notification']);
```

## 🧪 Testing

The package includes comprehensive tests:

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit tests/Unit/Authentication/
./vendor/bin/phpunit tests/Feature/WebSocket/

# Run with coverage
composer test-coverage

# Static analysis
composer analyse

# Code formatting
composer format
```

## 🎨 UI Themes and Customization

### Available Themes

- `default` - Clean, minimal design
- `dark` - Dark mode optimized
- `material` - Material design inspired
- `minimal` - Ultra-clean, minimal UI
- `glass` - Modern glassmorphism effect
- `success/info/warning/error` - Type-specific themes

### Mobile Optimization

The package includes full mobile support:
- Touch-friendly interactions
- Swipe-to-dismiss gestures
- Responsive design
- Progressive Web App features
- Background notifications via Service Workers

## 🚀 Deployment

### Production Configuration

```nginx
# Nginx configuration for WebSocket proxy
upstream websocket {
    server 127.0.0.1:8080;
    server 127.0.0.1:8081;
}

server {
    listen 443 ssl;
    server_name your-domain.com;
    
    location /ws {
        proxy_pass http://websocket;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

### Supervisor Configuration

```ini
[program:laravel-websocket]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan websocket:start
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/websocket.log
stopwaitsecs=10
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## 📄 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 👥 Credits

- **Author**: [Your Name](https://github.com/your-username)
- **Contributors**: [All Contributors](../../contributors)
- **Inspired by**: Laravel Echo, Socket.IO, Pusher

## ⭐ Support

If you find this package helpful, please consider giving it a star on GitHub!

---

**Laravel Notify** - Production-ready real-time notifications for Laravel applications.