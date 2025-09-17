<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the WebSocket server settings including
    | host, port, and authentication options for real-time communications.
    |
    */

    'server' => [
        'host' => env('WEBSOCKET_HOST', '0.0.0.0'),
        'port' => env('WEBSOCKET_PORT', 8080),
        'timeout' => env('WEBSOCKET_TIMEOUT', 60),
        'max_connections' => env('WEBSOCKET_MAX_CONNECTIONS', 1000),
        'heartbeat_interval' => env('WEBSOCKET_HEARTBEAT_INTERVAL', 30),
        'compression' => env('WEBSOCKET_COMPRESSION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration for Multi-Server Support
    |--------------------------------------------------------------------------
    |
    | Configure Redis for sharing connection state across multiple servers.
    |
    */

    'redis' => [
        'enabled' => env('WEBSOCKET_REDIS_ENABLED', false),
        'connection' => env('WEBSOCKET_REDIS_CONNECTION', 'default'),
        'prefix' => env('WEBSOCKET_REDIS_PREFIX', 'laravel_notify:'),
        'ttl' => env('WEBSOCKET_REDIS_TTL', 3600),
        'cluster_id' => env('WEBSOCKET_CLUSTER_ID', gethostname()),
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Balancer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure multiple WebSocket servers for load balancing.
    |
    */

    'load_balancer' => [
        'enabled' => env('WEBSOCKET_LOAD_BALANCER_ENABLED', false),
        'servers' => [
            [
                'host' => env('WEBSOCKET_LB_SERVER1_HOST', '127.0.0.1'),
                'port' => env('WEBSOCKET_LB_SERVER1_PORT', 8080),
                'weight' => env('WEBSOCKET_LB_SERVER1_WEIGHT', 1),
            ],
            [
                'host' => env('WEBSOCKET_LB_SERVER2_HOST', '127.0.0.1'),
                'port' => env('WEBSOCKET_LB_SERVER2_PORT', 8081),
                'weight' => env('WEBSOCKET_LB_SERVER2_WEIGHT', 1),
            ],
        ],
        'health_check_interval' => env('WEBSOCKET_HEALTH_CHECK_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for connections and messages.
    |
    */

    'rate_limiting' => [
        'enabled' => env('WEBSOCKET_RATE_LIMITING_ENABLED', true),
        'messages_per_minute' => env('WEBSOCKET_MESSAGES_PER_MINUTE', 60),
        'connections_per_ip' => env('WEBSOCKET_CONNECTIONS_PER_IP', 10),
        'burst_limit' => env('WEBSOCKET_BURST_LIMIT', 10),
        'whitelist' => array_filter(explode(',', env('WEBSOCKET_RATE_LIMIT_WHITELIST', '127.0.0.1,::1'))),
        'blacklist' => array_filter(explode(',', env('WEBSOCKET_RATE_LIMIT_BLACKLIST', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security features like IP filtering and geo-blocking.
    |
    */

    'security' => [
        'ip_whitelist' => array_filter(explode(',', env('WEBSOCKET_IP_WHITELIST', ''))),
        'ip_blacklist' => array_filter(explode(',', env('WEBSOCKET_IP_BLACKLIST', ''))),
        'geo_blocking' => [
            'enabled' => env('WEBSOCKET_GEO_BLOCKING_ENABLED', false),
            'allowed_countries' => array_filter(explode(',', env('WEBSOCKET_ALLOWED_COUNTRIES', ''))),
            'blocked_countries' => array_filter(explode(',', env('WEBSOCKET_BLOCKED_COUNTRIES', ''))),
        ],
        'ddos_protection' => [
            'enabled' => env('WEBSOCKET_DDOS_PROTECTION', true),
            'max_requests_per_second' => env('WEBSOCKET_MAX_REQUESTS_PER_SECOND', 100),
            'ban_duration' => env('WEBSOCKET_BAN_DURATION', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configure how clients authenticate with the WebSocket server.
    |
    */

    'auth' => [
        'enabled' => env('WEBSOCKET_AUTH_ENABLED', true),
        'secret' => env('WEBSOCKET_SECRET', env('APP_KEY')),
        'token_expiry' => env('WEBSOCKET_TOKEN_EXPIRY', 3600), // 1 hour
        'providers' => [
            'jwt' => LaravelNotify\Auth\JWTAuthProvider::class,
            'sanctum' => LaravelNotify\Auth\SanctumAuthProvider::class,
            'session' => LaravelNotify\Auth\SessionAuthProvider::class,
        ],
        'default_provider' => env('WEBSOCKET_AUTH_PROVIDER', 'jwt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Persistence Configuration
    |--------------------------------------------------------------------------
    |
    | Configure message persistence for offline delivery.
    |
    */

    'message_persistence' => [
        'enabled' => env('WEBSOCKET_MESSAGE_PERSISTENCE', false),
        'driver' => env('WEBSOCKET_PERSISTENCE_DRIVER', 'database'), // database, redis, file
        'ttl' => env('WEBSOCKET_MESSAGE_TTL', 86400), // 24 hours
        'offline_delivery' => env('WEBSOCKET_OFFLINE_DELIVERY', true),
        'max_stored_messages' => env('WEBSOCKET_MAX_STORED_MESSAGES', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Acknowledgments
    |--------------------------------------------------------------------------
    |
    | Configure message delivery acknowledgments.
    |
    */

    'acknowledgments' => [
        'enabled' => env('WEBSOCKET_ACKNOWLEDGMENTS', false),
        'timeout' => env('WEBSOCKET_ACK_TIMEOUT', 5),
        'retry_attempts' => env('WEBSOCKET_ACK_RETRIES', 3),
        'retry_interval' => env('WEBSOCKET_ACK_RETRY_INTERVAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which channels and events are allowed to be broadcast
    | through the WebSocket server.
    |
    */

    'broadcasting' => [
        'default_channel' => 'notifications',
        'allowed_channels' => [
            'notifications',
            'chat',
            'alerts',
            'updates',
            'dashboard',
        ],
        'private_channels' => [
            'user.*',
            'chat.*',
            'admin.*',
            'manager.*',
        ],
        'presence_channels' => [
            'chat.*',
            'room.*',
        ],
        'filters' => [
            'before_broadcast' => [
                // LaravelNotify\Filters\MessageSanitizer::class,
                // LaravelNotify\Filters\ContentModerator::class,
            ],
            'after_broadcast' => [
                // LaravelNotify\Filters\DeliveryTracker::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection and export.
    |
    */

    'metrics' => [
        'enabled' => env('WEBSOCKET_METRICS_ENABLED', true),
        'collectors' => [
            LaravelNotify\Metrics\ConnectionMetrics::class,
            LaravelNotify\Metrics\MessageMetrics::class,
            LaravelNotify\Metrics\PerformanceMetrics::class,
        ],
        'export' => [
            'prometheus' => [
                'enabled' => env('WEBSOCKET_PROMETHEUS_ENABLED', false),
                'host' => env('WEBSOCKET_PROMETHEUS_HOST', '127.0.0.1'),
                'port' => env('WEBSOCKET_PROMETHEUS_PORT', 9090),
                'job' => env('WEBSOCKET_PROMETHEUS_JOB', 'laravel_notify'),
            ],
            'influxdb' => [
                'enabled' => env('WEBSOCKET_INFLUXDB_ENABLED', false),
                'host' => env('WEBSOCKET_INFLUXDB_HOST', '127.0.0.1'),
                'port' => env('WEBSOCKET_INFLUXDB_PORT', 8086),
                'database' => env('WEBSOCKET_INFLUXDB_DATABASE', 'websocket'),
            ],
        ],
        'retention' => env('WEBSOCKET_METRICS_RETENTION', 604800), // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check endpoints and monitoring.
    |
    */

    'health_check' => [
        'enabled' => env('WEBSOCKET_HEALTH_CHECK_ENABLED', true),
        'endpoint' => env('WEBSOCKET_HEALTH_ENDPOINT', '/websocket/health'),
        'auth_required' => env('WEBSOCKET_HEALTH_AUTH_REQUIRED', false),
        'checks' => [
            'connection_count' => true,
            'memory_usage' => true,
            'redis_connection' => true,
            'database_connection' => false,
        ],
        'thresholds' => [
            'max_memory_mb' => env('WEBSOCKET_MAX_MEMORY_MB', 512),
            'max_connections' => env('WEBSOCKET_MAX_CONNECTIONS_HEALTH', 900),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging settings for the WebSocket server.
    |
    */

    'logging' => [
        'enabled' => env('WEBSOCKET_LOGGING', true),
        'level' => env('WEBSOCKET_LOG_LEVEL', 'info'),
        'file' => storage_path('logs/websocket.log'),
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path' => storage_path('logs/websocket.log'),
                'level' => env('WEBSOCKET_LOG_LEVEL', 'info'),
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => storage_path('logs/websocket.log'),
                'level' => env('WEBSOCKET_LOG_LEVEL', 'info'),
                'days' => 14,
            ],
        ],
        'format' => env('WEBSOCKET_LOG_FORMAT', 'json'), // json, line
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SSL settings for secure WebSocket connections (wss://).
    |
    */

    'ssl' => [
        'enabled' => env('WEBSOCKET_SSL_ENABLED', false),
        'cert_path' => env('WEBSOCKET_SSL_CERT'),
        'key_path' => env('WEBSOCKET_SSL_KEY'),
        'ca_path' => env('WEBSOCKET_SSL_CA'),
        'verify_peer' => env('WEBSOCKET_SSL_VERIFY_PEER', true),
        'allow_self_signed' => env('WEBSOCKET_SSL_ALLOW_SELF_SIGNED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Cross-Origin Resource Sharing for WebSocket connections.
    |
    */

    'cors' => [
        'allowed_origins' => array_filter(explode(',', env('WEBSOCKET_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost')))),
        'allowed_headers' => [
            'Authorization',
            'Content-Type',
            'X-Auth-Token',
            'X-Requested-With',
            'Origin',
        ],
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allow_credentials' => env('WEBSOCKET_ALLOW_CREDENTIALS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue integration for heavy broadcasts.
    |
    */

    'queue' => [
        'enabled' => env('WEBSOCKET_QUEUE_ENABLED', false),
        'connection' => env('WEBSOCKET_QUEUE_CONNECTION', 'redis'),
        'queue' => env('WEBSOCKET_QUEUE_NAME', 'websocket_broadcasts'),
        'retry_after' => env('WEBSOCKET_QUEUE_RETRY_AFTER', 90),
        'max_tries' => env('WEBSOCKET_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin System Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the plugin system for third-party extensions.
    |
    */

    'plugins' => [
        'enabled' => env('WEBSOCKET_PLUGINS_ENABLED', false),
        'path' => base_path('websocket-plugins'),
        'auto_discover' => env('WEBSOCKET_PLUGINS_AUTO_DISCOVER', true),
        'registered' => [
            // LaravelNotify\Plugins\ChatPlugin::class,
            // LaravelNotify\Plugins\NotificationPlugin::class,
        ],
    ],
];