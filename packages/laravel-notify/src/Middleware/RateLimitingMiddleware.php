<?php

namespace LaravelNotify\Middleware;

use Ratchet\ConnectionInterface;
use LaravelNotify\Contracts\ConnectionStateInterface;

class RateLimitingMiddleware
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;
    protected array $connectionCounts = [];
    protected array $messageCounts = [];

    public function __construct(ConnectionStateInterface $connectionState, array $config)
    {
        $this->connectionState = $connectionState;
        $this->config = $config;
    }

    /**
     * Check if connection is allowed
     */
    public function checkConnection(ConnectionInterface $conn): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $ip = $this->getClientIp($conn);
        
        // Check whitelist
        if ($this->isWhitelisted($ip)) {
            return true;
        }

        // Check blacklist
        if ($this->isBlacklisted($ip)) {
            return false;
        }

        // Check connection limit per IP
        $connectionCount = $this->connectionState->incrementRateLimit("connections:{$ip}", 60);
        
        if ($connectionCount > $this->config['connections_per_ip']) {
            $this->logRateLimitExceeded($ip, 'connections', $connectionCount);
            return false;
        }

        return true;
    }

    /**
     * Check if message is allowed
     */
    public function checkMessage(ConnectionInterface $conn): bool
    {
        if (!$this->config['enabled']) {
            return true;
        }

        $ip = $this->getClientIp($conn);
        
        // Check whitelist
        if ($this->isWhitelisted($ip)) {
            return true;
        }

        // Check message rate limit
        $messageCount = $this->connectionState->incrementRateLimit("messages:{$ip}", 60);
        
        if ($messageCount > $this->config['messages_per_minute']) {
            $this->logRateLimitExceeded($ip, 'messages', $messageCount);
            return false;
        }

        // Check burst limit (messages per second)
        $burstCount = $this->connectionState->incrementRateLimit("burst:{$ip}", 1);
        
        if ($burstCount > $this->config['burst_limit']) {
            $this->logRateLimitExceeded($ip, 'burst', $burstCount);
            return false;
        }

        return true;
    }

    /**
     * Check if IP is whitelisted
     */
    protected function isWhitelisted(string $ip): bool
    {
        foreach ($this->config['whitelist'] as $whitelistIp) {
            if ($this->matchesIpPattern($ip, $whitelistIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is blacklisted
     */
    protected function isBlacklisted(string $ip): bool
    {
        foreach ($this->config['blacklist'] as $blacklistIp) {
            if ($this->matchesIpPattern($ip, $blacklistIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match IP against pattern (supports wildcards)
     */
    protected function matchesIpPattern(string $ip, string $pattern): bool
    {
        if ($pattern === $ip) {
            return true;
        }

        // Support wildcards like 192.168.1.*
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match('/^' . $pattern . '$/', $ip);
    }

    /**
     * Get client IP address
     */
    protected function getClientIp(ConnectionInterface $conn): string
    {
        // Try to get real IP from headers
        $request = $conn->httpRequest ?? null;
        
        if ($request) {
            $headers = $request->getHeaders();
            
            // Check X-Forwarded-For header
            if (isset($headers['x-forwarded-for'])) {
                $ips = explode(',', $headers['x-forwarded-for'][0]);
                return trim($ips[0]);
            }
            
            // Check X-Real-IP header
            if (isset($headers['x-real-ip'])) {
                return $headers['x-real-ip'][0];
            }
        }

        // Fall back to connection remote address
        return $conn->remoteAddress ?? '127.0.0.1';
    }

    /**
     * Log rate limit exceeded event
     */
    protected function logRateLimitExceeded(string $ip, string $type, int $count): void
    {
        $message = "Rate limit exceeded for IP {$ip}: {$type} limit reached ({$count})";
        
        if (function_exists('logger')) {
            logger('websocket')->warning($message, [
                'ip' => $ip,
                'type' => $type,
                'count' => $count,
                'limit' => $this->config["{$type}_per_minute"] ?? $this->config["{$type}_limit"] ?? 'unknown',
            ]);
        } else {
            error_log($message);
        }
    }

    /**
     * Get rate limit status for IP
     */
    public function getRateLimitStatus(string $ip): array
    {
        return [
            'connections' => [
                'count' => $this->connectionState->getRateLimitCount("connections:{$ip}"),
                'limit' => $this->config['connections_per_ip'],
            ],
            'messages' => [
                'count' => $this->connectionState->getRateLimitCount("messages:{$ip}"),
                'limit' => $this->config['messages_per_minute'],
            ],
            'burst' => [
                'count' => $this->connectionState->getRateLimitCount("burst:{$ip}"),
                'limit' => $this->config['burst_limit'],
            ],
        ];
    }

    /**
     * Clear rate limits for IP (admin function)
     */
    public function clearRateLimits(string $ip): bool
    {
        // This would need to be implemented in the connection state
        // For now, just return true
        return true;
    }
}