<?php

namespace LaravelNotify\Server;

use Ratchet\ConnectionInterface;

class ConnectionManager
{
    protected array $connections = [];
    protected array $channels = [];
    protected array $authenticatedUsers = [];

    /**
     * Add a new connection
     */
    public function addConnection(ConnectionInterface $conn): void
    {
        $this->connections[$conn->resourceId] = [
            'connection' => $conn,
            'channels' => [],
            'user' => null,
            'authenticated' => false,
            'connected_at' => time(),
        ];
    }

    /**
     * Remove a connection
     */
    public function removeConnection(ConnectionInterface $conn): void
    {
        $resourceId = $conn->resourceId;
        
        // Remove from all channels
        if (isset($this->connections[$resourceId])) {
            $channels = $this->connections[$resourceId]['channels'];
            foreach ($channels as $channel) {
                $this->unsubscribeFromChannel($conn, $channel);
            }
        }

        unset($this->connections[$resourceId]);
    }

    /**
     * Authenticate a connection with user data
     */
    public function authenticateConnection(ConnectionInterface $conn, array $user): void
    {
        $resourceId = $conn->resourceId;
        
        if (isset($this->connections[$resourceId])) {
            $this->connections[$resourceId]['user'] = $user;
            $this->connections[$resourceId]['authenticated'] = true;
            $this->authenticatedUsers[$user['id']] = $resourceId;
        }
    }

    /**
     * Check if connection is authenticated
     */
    public function isAuthenticated(ConnectionInterface $conn): bool
    {
        $resourceId = $conn->resourceId;
        return $this->connections[$resourceId]['authenticated'] ?? false;
    }

    /**
     * Get user data for connection
     */
    public function getUser(ConnectionInterface $conn): ?array
    {
        $resourceId = $conn->resourceId;
        return $this->connections[$resourceId]['user'] ?? null;
    }

    /**
     * Subscribe connection to a channel
     */
    public function subscribeToChannel(ConnectionInterface $conn, string $channel): void
    {
        $resourceId = $conn->resourceId;

        // Add channel to connection
        if (isset($this->connections[$resourceId])) {
            $this->connections[$resourceId]['channels'][] = $channel;
        }

        // Add connection to channel
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
        $this->channels[$channel][$resourceId] = $conn;
    }

    /**
     * Unsubscribe connection from a channel
     */
    public function unsubscribeFromChannel(ConnectionInterface $conn, string $channel): void
    {
        $resourceId = $conn->resourceId;

        // Remove channel from connection
        if (isset($this->connections[$resourceId])) {
            $channels = &$this->connections[$resourceId]['channels'];
            $channels = array_filter($channels, fn($ch) => $ch !== $channel);
        }

        // Remove connection from channel
        if (isset($this->channels[$channel][$resourceId])) {
            unset($this->channels[$channel][$resourceId]);
            
            // Clean up empty channels
            if (empty($this->channels[$channel])) {
                unset($this->channels[$channel]);
            }
        }
    }

    /**
     * Get all subscribers to a channel
     */
    public function getChannelSubscribers(string $channel): array
    {
        return $this->channels[$channel] ?? [];
    }

    /**
     * Get connection by user ID
     */
    public function getConnectionByUserId(int $userId): ?ConnectionInterface
    {
        $resourceId = $this->authenticatedUsers[$userId] ?? null;
        
        if ($resourceId && isset($this->connections[$resourceId])) {
            return $this->connections[$resourceId]['connection'];
        }
        
        return null;
    }

    /**
     * Get all connections
     */
    public function getAllConnections(): array
    {
        return array_column($this->connections, 'connection');
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'total_connections' => count($this->connections),
            'authenticated_connections' => count(array_filter($this->connections, fn($c) => $c['authenticated'])),
            'total_channels' => count($this->channels),
            'channels' => array_map(fn($subscribers) => count($subscribers), $this->channels),
        ];
    }

    /**
     * Send message to user by ID
     */
    public function sendToUser(int $userId, array $message): bool
    {
        $conn = $this->getConnectionByUserId($userId);
        
        if ($conn) {
            $conn->send(json_encode([
                'type' => 'notification',
                'data' => $message,
                'timestamp' => time()
            ]));
            return true;
        }
        
        return false;
    }
}