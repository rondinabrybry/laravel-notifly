<?php

namespace LaravelNotify\Authentication\Providers;

use LaravelNotify\Contracts\AuthenticationProviderInterface;

class SessionAuthProvider implements AuthenticationProviderInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'session_driver' => 'file',
            'session_cookie' => 'laravel_session',
            'csrf_token_key' => '_token',
            'require_csrf' => true,
            'user_provider' => 'users',
        ], $config);
    }

    /**
     * Authenticate WebSocket connection using Laravel session
     */
    public function authenticate(string $token, array $context = []): ?array
    {
        try {
            // Parse session data from token
            $sessionData = $this->parseSessionToken($token);
            
            if (!$sessionData) {
                return null;
            }

            // Get session ID and CSRF token
            $sessionId = $sessionData['session_id'] ?? null;
            $csrfToken = $sessionData['csrf_token'] ?? null;

            if (!$sessionId) {
                return null;
            }

            // Verify CSRF token if required
            if ($this->config['require_csrf'] && !$this->verifyCsrfToken($csrfToken, $sessionId)) {
                return null;
            }

            // Load session data
            $sessionStore = $this->getSessionStore();
            $sessionStore->setId($sessionId);
            
            if (!$sessionStore->isStarted()) {
                $sessionStore->start();
            }

            // Check if user is authenticated in session
            $userId = $sessionStore->get('login_web_' . sha1(config('app.name')));
            
            if (!$userId) {
                return null;
            }

            // Get user information
            $user = $this->getUserById($userId);
            
            if (!$user) {
                return null;
            }

            // Update session last activity
            $sessionStore->put('last_activity', time());
            $sessionStore->save();

            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'session_id' => $sessionId,
                'authenticated_at' => now()->toISOString(),
                'provider' => 'session',
            ];

        } catch (\Exception $e) {
            \Log::error('Session WebSocket authentication failed', [
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Parse session token from WebSocket connection
     */
    protected function parseSessionToken(string $token): ?array
    {
        try {
            // Token should be base64 encoded JSON with session_id and csrf_token
            $decoded = base64_decode($token);
            
            if (!$decoded) {
                return null;
            }

            $data = json_decode($decoded, true);
            
            if (!is_array($data)) {
                return null;
            }

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify CSRF token against session
     */
    protected function verifyCsrfToken(?string $csrfToken, string $sessionId): bool
    {
        if (!$csrfToken) {
            return false;
        }

        try {
            $sessionStore = $this->getSessionStore();
            $sessionStore->setId($sessionId);
            $sessionStore->start();

            $sessionCsrfToken = $sessionStore->get($this->config['csrf_token_key']);
            
            return hash_equals($sessionCsrfToken, $csrfToken);

        } catch (\Exception $e) {
            \Log::warning('CSRF token verification failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);
            
            return false;
        }
    }

    /**
     * Get Laravel session store
     */
    protected function getSessionStore()
    {
        $sessionManager = app('session');
        
        return $sessionManager->driver($this->config['session_driver']);
    }

    /**
     * Get user information by ID
     */
    public function getUserById(int $userId): ?array
    {
        try {
            $userProvider = app('auth')->createUserProvider($this->config['user_provider']);
            $user = $userProvider->retrieveById($userId);
            
            if (!$user) {
                return null;
            }

            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? 'User',
                'email' => $user->email ?? null,
                'provider' => 'session',
            ];

        } catch (\Exception $e) {
            \Log::error('Failed to get session user by ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Generate session token for WebSocket authentication
     */
    public function generateSessionToken(?string $sessionId = null, ?string $csrfToken = null): string
    {
        $sessionId = $sessionId ?? session()->getId();
        $csrfToken = $csrfToken ?? csrf_token();

        $data = [
            'session_id' => $sessionId,
            'csrf_token' => $csrfToken,
            'generated_at' => time(),
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Check if session is valid and authenticated
     */
    public function isSessionAuthenticated(string $sessionId): bool
    {
        try {
            $sessionStore = $this->getSessionStore();
            $sessionStore->setId($sessionId);
            $sessionStore->start();

            $userId = $sessionStore->get('login_web_' . sha1(config('app.name')));
            
            return !empty($userId);

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current authenticated user from session
     */
    public function getCurrentUser(): ?array
    {
        try {
            if (!auth()->check()) {
                return null;
            }

            $user = auth()->user();

            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? 'User',
                'email' => $user->email ?? null,
                'provider' => 'session',
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}