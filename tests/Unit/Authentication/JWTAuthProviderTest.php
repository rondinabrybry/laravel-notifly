<?php

namespace LaravelNotify\Tests\Unit\Authentication;

use LaravelNotify\Tests\TestCase;
use LaravelNotify\Authentication\Providers\JWTAuthProvider;

class JWTAuthProviderTest extends TestCase
{
    protected JWTAuthProvider $jwtProvider;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->jwtProvider = new JWTAuthProvider([
            'secret' => 'test-secret-key-for-testing',
            'algorithm' => 'HS256',
            'expires_in' => 3600,
        ]);
    }

    /** @test */
    public function it_can_generate_jwt_token()
    {
        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $token = $this->jwtProvider->generateToken($payload);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token); // JWT format has dots
    }

    /** @test */
    public function it_can_authenticate_valid_jwt_token()
    {
        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $token = $this->jwtProvider->generateToken($payload);
        $authData = $this->jwtProvider->authenticate($token);

        $this->assertWebSocketAuthenticated($authData);
        $this->assertEquals(1, $authData['id']);
        $this->assertEquals('Test User', $authData['name']);
        $this->assertEquals('test@example.com', $authData['email']);
        $this->assertEquals('jwt', $authData['provider']);
    }

    /** @test */
    public function it_rejects_invalid_jwt_token()
    {
        $invalidToken = 'invalid.jwt.token';
        $authData = $this->jwtProvider->authenticate($invalidToken);

        $this->assertWebSocketNotAuthenticated($authData);
    }

    /** @test */
    public function it_rejects_expired_jwt_token()
    {
        // Create provider with very short expiration
        $shortExpiryProvider = new JWTAuthProvider([
            'secret' => 'test-secret-key-for-testing',
            'algorithm' => 'HS256',
            'expires_in' => -1, // Already expired
        ]);

        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $token = $shortExpiryProvider->generateToken($payload);
        $authData = $shortExpiryProvider->authenticate($token);

        $this->assertWebSocketNotAuthenticated($authData);
    }

    /** @test */
    public function it_rejects_token_with_wrong_secret()
    {
        $wrongSecretProvider = new JWTAuthProvider([
            'secret' => 'wrong-secret-key',
            'algorithm' => 'HS256',
            'expires_in' => 3600,
        ]);

        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        // Generate with correct provider
        $token = $this->jwtProvider->generateToken($payload);
        
        // Verify with wrong provider
        $authData = $wrongSecretProvider->authenticate($token);

        $this->assertWebSocketNotAuthenticated($authData);
    }

    /** @test */
    public function it_validates_required_claims()
    {
        $incompletePayload = [
            'name' => 'Test User',
            // Missing user_id
        ];

        $token = $this->jwtProvider->generateToken($incompletePayload);
        $authData = $this->jwtProvider->authenticate($token);

        $this->assertWebSocketNotAuthenticated($authData);
    }

    /** @test */
    public function it_handles_bearer_token_format()
    {
        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $token = $this->jwtProvider->generateToken($payload);
        $bearerToken = 'Bearer ' . $token;
        
        $authData = $this->jwtProvider->authenticate($bearerToken);

        $this->assertWebSocketAuthenticated($authData);
        $this->assertEquals(1, $authData['id']);
    }

    /** @test */
    public function it_can_refresh_jwt_token()
    {
        $payload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $originalToken = $this->jwtProvider->generateToken($payload);
        $refreshedToken = $this->jwtProvider->refreshToken($originalToken);

        $this->assertIsString($refreshedToken);
        $this->assertNotEquals($originalToken, $refreshedToken);

        // Both tokens should authenticate successfully
        $originalAuth = $this->jwtProvider->authenticate($originalToken);
        $refreshedAuth = $this->jwtProvider->authenticate($refreshedToken);

        $this->assertWebSocketAuthenticated($originalAuth);
        $this->assertWebSocketAuthenticated($refreshedAuth);
        $this->assertEquals($originalAuth['id'], $refreshedAuth['id']);
    }

    /** @test */
    public function it_can_get_user_by_id()
    {
        // This test would require a database setup or mocking
        // For now, we'll test that the method exists and handles errors gracefully
        
        $user = $this->jwtProvider->getUserById(999); // Non-existent user
        $this->assertNull($user);
    }

    /** @test */
    public function it_validates_token_structure()
    {
        $malformedTokens = [
            '',
            'not.enough.parts',
            'too.many.parts.here.and.more',
            'invalidbase64.invalidbase64.invalidbase64',
        ];

        foreach ($malformedTokens as $token) {
            $authData = $this->jwtProvider->authenticate($token);
            $this->assertWebSocketNotAuthenticated($authData);
        }
    }
}