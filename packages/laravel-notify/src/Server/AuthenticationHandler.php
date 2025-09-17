<?php

namespace LaravelNotify\Server;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthenticationHandler
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Authenticate user with JWT token
     */
    public function authenticate(string $token): ?array
    {
        if (!$this->config['enabled']) {
            return ['id' => 0, 'name' => 'Guest', 'email' => 'guest@example.com'];
        }

        try {
            $decoded = JWT::decode($token, new Key($this->config['secret'], 'HS256'));
            
            if ($this->isTokenExpired($decoded)) {
                return null;
            }

            return [
                'id' => $decoded->user_id ?? $decoded->id ?? 0,
                'name' => $decoded->name ?? 'Unknown',
                'email' => $decoded->email ?? 'unknown@example.com',
                'roles' => $decoded->roles ?? [],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate JWT token for user
     */
    public function generateToken(array $user): string
    {
        $payload = [
            'user_id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'roles' => $user['roles'] ?? [],
            'iat' => time(),
            'exp' => time() + $this->config['token_expiry'],
        ];

        return JWT::encode($payload, $this->config['secret'], 'HS256');
    }

    /**
     * Check if token is expired
     */
    protected function isTokenExpired($decoded): bool
    {
        return isset($decoded->exp) && $decoded->exp < time();
    }

    /**
     * Validate channel access for user
     */
    public function canAccessChannel(array $user, string $channel): bool
    {
        // Public channels are always accessible
        if (!$this->isPrivateChannel($channel)) {
            return true;
        }

        // Private user channels - check if user owns the channel
        if (str_starts_with($channel, 'user.')) {
            $channelUserId = substr($channel, 5);
            return (string)$user['id'] === $channelUserId;
        }

        // Chat channels - implement your own logic
        if (str_starts_with($channel, 'chat.')) {
            // You can implement chat room membership logic here
            return true; // For now, allow all authenticated users
        }

        return false;
    }

    /**
     * Check if channel is private
     */
    protected function isPrivateChannel(string $channel): bool
    {
        $privatePatterns = [
            'user.*',
            'chat.*',
            'private.*'
        ];

        foreach ($privatePatterns as $pattern) {
            if (fnmatch($pattern, $channel)) {
                return true;
            }
        }

        return false;
    }
}