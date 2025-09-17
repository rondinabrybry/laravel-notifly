<?php

namespace LaravelNotify\Contracts;

interface ConnectionStateInterface
{
    /**
     * Store connection information
     */
    public function storeConnection(string $connectionId, array $data): bool;

    /**
     * Get connection information
     */
    public function getConnection(string $connectionId): ?array;

    /**
     * Remove connection
     */
    public function removeConnection(string $connectionId): bool;

    /**
     * Get all connections for a user
     */
    public function getUserConnections(int $userId): array;

    /**
     * Store channel subscription
     */
    public function subscribeToChannel(string $connectionId, string $channel, int $userId = null): bool;

    /**
     * Unsubscribe from channel
     */
    public function unsubscribeFromChannel(string $connectionId, string $channel, int $userId = null): bool;

    /**
     * Get channel subscribers
     */
    public function getChannelSubscribers(string $channel): array;

    /**
     * Get connection's subscriptions
     */
    public function getConnectionSubscriptions(string $connectionId): array;

    /**
     * Store message for offline delivery
     */
    public function storeOfflineMessage(int $userId, array $message): bool;

    /**
     * Get offline messages for user
     */
    public function getOfflineMessages(int $userId): array;

    /**
     * Clear offline messages for user
     */
    public function clearOfflineMessages(int $userId): bool;

    /**
     * Store rate limiting data
     */
    public function incrementRateLimit(string $identifier, int $window = 60): int;

    /**
     * Get rate limit count
     */
    public function getRateLimitCount(string $identifier): int;

    /**
     * Store metrics data
     */
    public function storeMetric(string $metric, $value, array $tags = []): bool;

    /**
     * Get server statistics
     */
    public function getServerStats(): array;

    /**
     * Cleanup expired data
     */
    public function cleanup(): int;
}