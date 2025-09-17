<?php

namespace LaravelNotify\Authentication\Providers;

use LaravelNotify\Contracts\AuthenticationProviderInterface;

class SanctumAuthProvider implements AuthenticationProviderInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'token_name' => 'websocket_token',
            'abilities' => ['websocket:connect'],
            'model' => null, // Will use default Sanctum user provider
        ], $config);
    }

    /**
     * Authenticate WebSocket connection
     */
    public function authenticate(string $token, array $context = []): ?array
    {
        try {
            // Parse token from Authorization header or query parameter
            $bearerToken = $this->parseToken($token);
            
            if (!$bearerToken) {
                return null;
            }

            // Find personal access token
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
            
            if (!$tokenModel) {
                return null;
            }

            // Check token abilities
            if (!$this->hasRequiredAbilities($tokenModel)) {
                return null;
            }

            // Check if token is expired (if using expiration)
            if ($this->isTokenExpired($tokenModel)) {
                return null;
            }

            // Get the authenticated user
            $user = $tokenModel->tokenable;
            
            if (!$user) {
                return null;
            }

            // Update last used timestamp
            $tokenModel->forceFill(['last_used_at' => now()])->save();

            return [
                'id' => $user->getKey(),
                'name' => $user->name ?? 'User',
                'email' => $user->email ?? null,
                'token_id' => $tokenModel->id,
                'token_name' => $tokenModel->name,
                'abilities' => $tokenModel->abilities,
                'authenticated_at' => now()->toISOString(),
                'provider' => 'sanctum',
            ];

        } catch (\Exception $e) {
            \Log::error('Sanctum WebSocket authentication failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 10) . '...',
            ]);
            
            return null;
        }
    }

    /**
     * Validate token format and extract bearer token
     */
    protected function parseToken(string $token): ?string
    {
        // Handle Authorization: Bearer {token} format
        if (str_starts_with($token, 'Bearer ')) {
            return substr($token, 7);
        }

        // Handle plain token
        if (strlen($token) >= 40) {
            return $token;
        }

        return null;
    }

    /**
     * Check if token has required abilities
     */
    protected function hasRequiredAbilities($tokenModel): bool
    {
        $requiredAbilities = $this->config['abilities'];
        
        if (empty($requiredAbilities)) {
            return true;
        }

        foreach ($requiredAbilities as $ability) {
            if (!$tokenModel->can($ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if token is expired (if expiration is enabled)
     */
    protected function isTokenExpired($tokenModel): bool
    {
        if (!config('sanctum.expiration')) {
            return false;
        }

        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = $tokenModel->created_at->addMinutes($expirationMinutes);
        
        return now()->greaterThan($expiresAt);
    }

    /**
     * Get user information by ID
     */
    public function getUserById(int $userId): ?array
    {
        try {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            
            if (!$user) {
                return null;
            }

            return [
                'id' => $user->getKey(),
                'name' => $user->name ?? 'User',
                'email' => $user->email ?? null,
                'provider' => 'sanctum',
            ];

        } catch (\Exception $e) {
            \Log::error('Failed to get Sanctum user by ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Generate authentication token for user
     */
    public function generateToken(int $userId, array $abilities = [], string $name = null): ?string
    {
        try {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            
            if (!$user) {
                return null;
            }

            $tokenName = $name ?? $this->config['token_name'];
            $tokenAbilities = empty($abilities) ? $this->config['abilities'] : $abilities;

            $token = $user->createToken($tokenName, $tokenAbilities);

            return $token->plainTextToken;

        } catch (\Exception $e) {
            \Log::error('Failed to generate Sanctum token', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
}