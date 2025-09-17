<?php

namespace LaravelNotify\Redis;

use Illuminate\Support\Facades\Redis;
use LaravelNotify\Contracts\ConnectionStateInterface;

class RedisConnectionState implements ConnectionStateInterface
{
    protected $redis;
    protected string $prefix;
    protected int $ttl;
    protected string $clusterId;

    public function __construct(array $config)
    {
        $this->redis = Redis::connection($config['connection'] ?? 'default');
        $this->prefix = $config['prefix'] ?? 'laravel_notify:';
        $this->ttl = $config['ttl'] ?? 3600;
        $this->clusterId = $config['cluster_id'] ?? gethostname();
    }

    /**
     * Store connection information
     */
    public function storeConnection(string $connectionId, array $data): bool
    {
        $key = $this->getConnectionKey($connectionId);
        $data['cluster_id'] = $this->clusterId;
        $data['last_seen'] = time();
        
        return $this->redis->setex($key, $this->ttl, json_encode($data));
    }

    /**
     * Get connection information
     */
    public function getConnection(string $connectionId): ?array
    {
        $key = $this->getConnectionKey($connectionId);
        $data = $this->redis->get($key);
        
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Remove connection
     */
    public function removeConnection(string $connectionId): bool
    {
        $key = $this->getConnectionKey($connectionId);
        return $this->redis->del($key) > 0;
    }

    /**
     * Get all connections for a user
     */
    public function getUserConnections(int $userId): array
    {
        $pattern = $this->prefix . "user:{$userId}:connections:*";
        $keys = $this->redis->keys($pattern);
        
        $connections = [];
        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $connections[] = json_decode($data, true);
            }
        }
        
        return $connections;
    }

    /**
     * Store channel subscription
     */
    public function subscribeToChannel(string $connectionId, string $channel, int $userId = null): bool
    {
        $channelKey = $this->getChannelKey($channel);
        $connectionKey = $this->getConnectionKey($connectionId);
        
        // Add connection to channel subscribers
        $this->redis->sadd($channelKey, $connectionId);
        
        // Add channel to connection's subscriptions
        $subscriptionsKey = $connectionKey . ':subscriptions';
        $this->redis->sadd($subscriptionsKey, $channel);
        
        // If user-specific, also track by user
        if ($userId) {
            $userChannelKey = $this->prefix . "user:{$userId}:channels";
            $this->redis->sadd($userChannelKey, $channel);
        }
        
        // Set expiry for cleanup
        $this->redis->expire($channelKey, $this->ttl);
        $this->redis->expire($subscriptionsKey, $this->ttl);
        
        return true;
    }

    /**
     * Unsubscribe from channel
     */
    public function unsubscribeFromChannel(string $connectionId, string $channel, int $userId = null): bool
    {
        $channelKey = $this->getChannelKey($channel);
        $connectionKey = $this->getConnectionKey($connectionId);
        
        // Remove connection from channel subscribers
        $this->redis->srem($channelKey, $connectionId);
        
        // Remove channel from connection's subscriptions
        $subscriptionsKey = $connectionKey . ':subscriptions';
        $this->redis->srem($subscriptionsKey, $channel);
        
        // If user-specific, also remove from user tracking
        if ($userId) {
            $userChannelKey = $this->prefix . "user:{$userId}:channels";
            $this->redis->srem($userChannelKey, $channel);
        }
        
        return true;
    }

    /**
     * Get channel subscribers
     */
    public function getChannelSubscribers(string $channel): array
    {
        $channelKey = $this->getChannelKey($channel);
        return $this->redis->smembers($channelKey);
    }

    /**
     * Get connection's subscriptions
     */
    public function getConnectionSubscriptions(string $connectionId): array
    {
        $subscriptionsKey = $this->getConnectionKey($connectionId) . ':subscriptions';
        return $this->redis->smembers($subscriptionsKey);
    }

    /**
     * Store message for offline delivery
     */
    public function storeOfflineMessage(int $userId, array $message): bool
    {
        $messageKey = $this->prefix . "offline_messages:user:{$userId}";
        $message['stored_at'] = time();
        
        $this->redis->lpush($messageKey, json_encode($message));
        $this->redis->expire($messageKey, 86400); // 24 hours
        
        // Limit number of stored messages
        $this->redis->ltrim($messageKey, 0, 999); // Keep only 1000 messages
        
        return true;
    }

    /**
     * Get offline messages for user
     */
    public function getOfflineMessages(int $userId): array
    {
        $messageKey = $this->prefix . "offline_messages:user:{$userId}";
        $messages = $this->redis->lrange($messageKey, 0, -1);
        
        $decodedMessages = [];
        foreach ($messages as $message) {
            $decodedMessages[] = json_decode($message, true);
        }
        
        return $decodedMessages;
    }

    /**
     * Clear offline messages for user
     */
    public function clearOfflineMessages(int $userId): bool
    {
        $messageKey = $this->prefix . "offline_messages:user:{$userId}";
        return $this->redis->del($messageKey) > 0;
    }

    /**
     * Store rate limiting data
     */
    public function incrementRateLimit(string $identifier, int $window = 60): int
    {
        $key = $this->prefix . "rate_limit:{$identifier}";
        
        $current = $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $window);
        }
        
        return $current;
    }

    /**
     * Get rate limit count
     */
    public function getRateLimitCount(string $identifier): int
    {
        $key = $this->prefix . "rate_limit:{$identifier}";
        return (int) $this->redis->get($key);
    }

    /**
     * Store metrics data
     */
    public function storeMetric(string $metric, $value, array $tags = []): bool
    {
        $timestamp = time();
        $key = $this->prefix . "metrics:{$metric}:" . date('Y-m-d-H-i', $timestamp);
        
        $data = [
            'value' => $value,
            'tags' => $tags,
            'timestamp' => $timestamp,
            'cluster_id' => $this->clusterId,
        ];
        
        $this->redis->lpush($key, json_encode($data));
        $this->redis->expire($key, 604800); // 7 days
        
        return true;
    }

    /**
     * Get server statistics
     */
    public function getServerStats(): array
    {
        $pattern = $this->prefix . "connections:*";
        $connectionKeys = $this->redis->keys($pattern);
        
        $stats = [
            'total_connections' => count($connectionKeys),
            'connections_by_cluster' => [],
            'active_channels' => 0,
            'memory_usage' => memory_get_usage(true),
            'uptime' => $this->getServerUptime(),
        ];
        
        // Count connections by cluster
        foreach ($connectionKeys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $connection = json_decode($data, true);
                $clusterId = $connection['cluster_id'] ?? 'unknown';
                $stats['connections_by_cluster'][$clusterId] = 
                    ($stats['connections_by_cluster'][$clusterId] ?? 0) + 1;
            }
        }
        
        // Count active channels
        $channelPattern = $this->prefix . "channels:*";
        $stats['active_channels'] = count($this->redis->keys($channelPattern));
        
        return $stats;
    }

    /**
     * Cleanup expired data
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        
        // Clean up expired connections
        $connectionPattern = $this->prefix . "connections:*";
        $connectionKeys = $this->redis->keys($connectionPattern);
        
        foreach ($connectionKeys as $key) {
            $ttl = $this->redis->ttl($key);
            if ($ttl <= 0) {
                $this->redis->del($key);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }

    /**
     * Get connection Redis key
     */
    protected function getConnectionKey(string $connectionId): string
    {
        return $this->prefix . "connections:{$connectionId}";
    }

    /**
     * Get channel Redis key
     */
    protected function getChannelKey(string $channel): string
    {
        return $this->prefix . "channels:{$channel}";
    }

    /**
     * Get server uptime
     */
    protected function getServerUptime(): int
    {
        $uptimeKey = $this->prefix . "server:uptime:{$this->clusterId}";
        $uptime = $this->redis->get($uptimeKey);
        
        if (!$uptime) {
            $uptime = time();
            $this->redis->set($uptimeKey, $uptime);
        }
        
        return time() - (int) $uptime;
    }
}