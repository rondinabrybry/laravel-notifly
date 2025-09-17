<?php

namespace LaravelNotify\Metrics;

use LaravelNotify\Contracts\ConnectionStateInterface;

class ConnectionMetrics
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;

    public function __construct(ConnectionStateInterface $connectionState, array $config)
    {
        $this->connectionState = $connectionState;
        $this->config = $config;
    }

    /**
     * Record connection opened
     */
    public function recordConnectionOpened(string $connectionId, array $metadata = []): void
    {
        $this->connectionState->storeMetric('connections_opened', 1, array_merge([
            'connection_id' => $connectionId,
            'timestamp' => time(),
        ], $metadata));
    }

    /**
     * Record connection closed
     */
    public function recordConnectionClosed(string $connectionId, string $reason = null): void
    {
        $this->connectionState->storeMetric('connections_closed', 1, [
            'connection_id' => $connectionId,
            'reason' => $reason,
            'timestamp' => time(),
        ]);
    }

    /**
     * Record authentication attempt
     */
    public function recordAuthAttempt(string $connectionId, bool $success, string $provider = null): void
    {
        $this->connectionState->storeMetric('auth_attempts', 1, [
            'connection_id' => $connectionId,
            'success' => $success,
            'provider' => $provider,
            'timestamp' => time(),
        ]);
    }

    /**
     * Record channel subscription
     */
    public function recordChannelSubscription(string $connectionId, string $channel, bool $success): void
    {
        $this->connectionState->storeMetric('channel_subscriptions', 1, [
            'connection_id' => $connectionId,
            'channel' => $channel,
            'success' => $success,
            'timestamp' => time(),
        ]);
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(): array
    {
        return $this->connectionState->getServerStats();
    }
}

class MessageMetrics
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;

    public function __construct(ConnectionStateInterface $connectionState, array $config)
    {
        $this->connectionState = $connectionState;
        $this->config = $config;
    }

    /**
     * Record message sent
     */
    public function recordMessageSent(string $channel, array $message, int $recipientCount = 1): void
    {
        $this->connectionState->storeMetric('messages_sent', 1, [
            'channel' => $channel,
            'type' => $message['type'] ?? 'unknown',
            'recipient_count' => $recipientCount,
            'message_size' => strlen(json_encode($message)),
            'timestamp' => time(),
        ]);
    }

    /**
     * Record message received
     */
    public function recordMessageReceived(string $connectionId, array $message): void
    {
        $this->connectionState->storeMetric('messages_received', 1, [
            'connection_id' => $connectionId,
            'type' => $message['type'] ?? 'unknown',
            'message_size' => strlen(json_encode($message)),
            'timestamp' => time(),
        ]);
    }

    /**
     * Record message acknowledgment
     */
    public function recordMessageAck(string $messageId, bool $success, float $latency = null): void
    {
        $this->connectionState->storeMetric('message_acknowledgments', 1, [
            'message_id' => $messageId,
            'success' => $success,
            'latency_ms' => $latency,
            'timestamp' => time(),
        ]);
    }

    /**
     * Record broadcast event
     */
    public function recordBroadcast(string $event, string $channel, int $recipientCount): void
    {
        $this->connectionState->storeMetric('broadcasts', 1, [
            'event' => $event,
            'channel' => $channel,
            'recipient_count' => $recipientCount,
            'timestamp' => time(),
        ]);
    }
}

class PerformanceMetrics
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;
    protected float $startTime;

    public function __construct(ConnectionStateInterface $connectionState, array $config)
    {
        $this->connectionState = $connectionState;
        $this->config = $config;
        $this->startTime = microtime(true);
    }

    /**
     * Record memory usage
     */
    public function recordMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        $this->connectionState->storeMetric('memory_usage', $memoryUsage, [
            'peak_usage' => $peakUsage,
            'timestamp' => time(),
        ]);
    }

    /**
     * Record CPU usage (if available)
     */
    public function recordCpuUsage(): void
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->connectionState->storeMetric('cpu_load', $load[0], [
                'load_5min' => $load[1],
                'load_15min' => $load[2],
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Record message processing time
     */
    public function recordProcessingTime(string $operation, float $duration): void
    {
        $this->connectionState->storeMetric('processing_time', $duration, [
            'operation' => $operation,
            'timestamp' => time(),
        ]);
    }

    /**
     * Record error occurrence
     */
    public function recordError(string $type, string $message, array $context = []): void
    {
        $this->connectionState->storeMetric('errors', 1, array_merge([
            'error_type' => $type,
            'error_message' => $message,
            'timestamp' => time(),
        ], $context));
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $uptime = time() - $this->startTime;
        
        return [
            'uptime_seconds' => $uptime,
            'memory_usage_bytes' => memory_get_usage(true),
            'peak_memory_usage_bytes' => memory_get_peak_usage(true),
            'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'timestamp' => time(),
        ];
    }
}

class MetricsExporter
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;

    public function __construct(ConnectionStateInterface $connectionState, array $config)
    {
        $this->connectionState = $connectionState;
        $this->config = $config;
    }

    /**
     * Export metrics to Prometheus
     */
    public function exportToPrometheus(): string
    {
        if (!$this->config['export']['prometheus']['enabled']) {
            return '';
        }

        $stats = $this->connectionState->getServerStats();
        $output = [];

        // Connection metrics
        $output[] = '# HELP websocket_connections_total Total number of WebSocket connections';
        $output[] = '# TYPE websocket_connections_total gauge';
        $output[] = "websocket_connections_total {$stats['total_connections']}";

        // Memory metrics
        $output[] = '# HELP websocket_memory_usage_bytes Memory usage in bytes';
        $output[] = '# TYPE websocket_memory_usage_bytes gauge';
        $output[] = "websocket_memory_usage_bytes {$stats['memory_usage']}";

        // Uptime metrics
        $output[] = '# HELP websocket_uptime_seconds Server uptime in seconds';
        $output[] = '# TYPE websocket_uptime_seconds counter';
        $output[] = "websocket_uptime_seconds {$stats['uptime']}";

        // Active channels
        $output[] = '# HELP websocket_active_channels Total number of active channels';
        $output[] = '# TYPE websocket_active_channels gauge';
        $output[] = "websocket_active_channels {$stats['active_channels']}";

        return implode("\n", $output) . "\n";
    }

    /**
     * Export metrics to InfluxDB
     */
    public function exportToInfluxDB(): bool
    {
        if (!$this->config['export']['influxdb']['enabled']) {
            return false;
        }

        $stats = $this->connectionState->getServerStats();
        $config = $this->config['export']['influxdb'];
        
        $data = [
            'measurement' => 'websocket_metrics',
            'time' => time() * 1000000000, // nanoseconds
            'fields' => [
                'connections' => $stats['total_connections'],
                'memory_usage' => $stats['memory_usage'],
                'uptime' => $stats['uptime'],
                'active_channels' => $stats['active_channels'],
            ],
            'tags' => [
                'server' => gethostname(),
                'version' => '1.0.0',
            ],
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post("http://{$config['host']}:{$config['port']}/write", [
                'query' => ['db' => $config['database']],
                'body' => $this->formatInfluxDBLine($data),
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ]);

            return $response->getStatusCode() === 204;
        } catch (\Exception $e) {
            error_log("Failed to export metrics to InfluxDB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format data for InfluxDB line protocol
     */
    protected function formatInfluxDBLine(array $data): string
    {
        $tags = [];
        foreach ($data['tags'] as $key => $value) {
            $tags[] = "{$key}={$value}";
        }

        $fields = [];
        foreach ($data['fields'] as $key => $value) {
            $fields[] = "{$key}={$value}";
        }

        return $data['measurement'] . 
               (empty($tags) ? '' : ',' . implode(',', $tags)) . 
               ' ' . implode(',', $fields) . 
               ' ' . $data['time'];
    }
}