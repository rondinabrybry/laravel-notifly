<?php

namespace LaravelNotify\Tests\Feature\WebSocket;

use LaravelNotify\Tests\TestCase;
use LaravelNotify\Server\WebSocketServer;
use LaravelNotify\Server\ConnectionManager;
use LaravelNotify\Authentication\AuthenticationHandler;
use LaravelNotify\Contracts\ConnectionStateInterface;

class WebSocketConnectionTest extends TestCase
{
    protected WebSocketServer $server;
    protected ConnectionManager $connectionManager;
    protected AuthenticationHandler $authHandler;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Redis if needed
        $this->mockRedis();
        
        // Set up WebSocket components
        $this->connectionManager = new ConnectionManager();
        $this->authHandler = new AuthenticationHandler();
        $this->server = new WebSocketServer($this->connectionManager, $this->authHandler);
    }

    /** @test */
    public function it_can_handle_new_websocket_connections()
    {
        $connection = $this->createMockWebSocketConnection();
        
        // Simulate connection opening
        $this->server->onOpen($connection);
        
        // Verify connection is tracked
        $this->assertTrue($this->connectionManager->hasConnection($connection->resourceId));
        $this->assertEquals(1, $this->connectionManager->getConnectionCount());
    }

    /** @test */
    public function it_can_handle_connection_closing()
    {
        $connection = $this->createMockWebSocketConnection();
        
        // Open and then close connection
        $this->server->onOpen($connection);
        $this->assertTrue($this->connectionManager->hasConnection($connection->resourceId));
        
        $this->server->onClose($connection);
        $this->assertFalse($this->connectionManager->hasConnection($connection->resourceId));
        $this->assertEquals(0, $this->connectionManager->getConnectionCount());
    }

    /** @test */
    public function it_authenticates_connections_with_valid_jwt()
    {
        $connection = $this->createMockWebSocketConnection();
        $token = $this->createJwtToken(['user_id' => 1, 'name' => 'Test User']);
        
        // Add authorization header
        $connection->httpHeaders = ['Authorization' => 'Bearer ' . $token];
        
        $this->server->onOpen($connection);
        
        // Verify connection is authenticated
        $connectionData = $this->connectionManager->getConnection($connection->resourceId);
        $this->assertNotNull($connectionData);
        $this->assertTrue($connectionData['authenticated']);
        $this->assertEquals(1, $connectionData['user']['id']);
    }

    /** @test */
    public function it_rejects_connections_with_invalid_jwt()
    {
        $connection = $this->createMockWebSocketConnection();
        $connection->httpHeaders = ['Authorization' => 'Bearer invalid-token'];
        
        $this->server->onOpen($connection);
        
        // Connection should exist but not be authenticated
        $connectionData = $this->connectionManager->getConnection($connection->resourceId);
        $this->assertNotNull($connectionData);
        $this->assertFalse($connectionData['authenticated']);
    }

    /** @test */
    public function it_can_handle_channel_subscriptions()
    {
        $connection = $this->createMockWebSocketConnection();
        $token = $this->createJwtToken(['user_id' => 1, 'name' => 'Test User']);
        $connection->httpHeaders = ['Authorization' => 'Bearer ' . $token];
        
        $this->server->onOpen($connection);
        
        // Subscribe to channel
        $subscribeMessage = json_encode([
            'type' => 'subscribe',
            'channel' => 'notifications.1',
        ]);
        
        $this->server->onMessage($connection, $subscribeMessage);
        
        // Verify subscription
        $channels = $this->connectionManager->getConnectionChannels($connection->resourceId);
        $this->assertContains('notifications.1', $channels);
    }

    /** @test */
    public function it_prevents_unauthorized_channel_subscriptions()
    {
        $connection = $this->createMockWebSocketConnection();
        
        // Connection without authentication
        $this->server->onOpen($connection);
        
        $subscribeMessage = json_encode([
            'type' => 'subscribe',
            'channel' => 'private.notifications.1',
        ]);
        
        $this->server->onMessage($connection, $subscribeMessage);
        
        // Should not be subscribed to private channel
        $channels = $this->connectionManager->getConnectionChannels($connection->resourceId);
        $this->assertNotContains('private.notifications.1', $channels);
    }

    /** @test */
    public function it_can_broadcast_messages_to_channels()
    {
        // Set up multiple connections
        $connection1 = $this->createMockWebSocketConnection();
        $connection1->resourceId = 1;
        $connection2 = $this->createMockWebSocketConnection();
        $connection2->resourceId = 2;
        
        $token = $this->createJwtToken(['user_id' => 1, 'name' => 'Test User']);
        $connection1->httpHeaders = ['Authorization' => 'Bearer ' . $token];
        $connection2->httpHeaders = ['Authorization' => 'Bearer ' . $token];
        
        // Open connections and subscribe to same channel
        $this->server->onOpen($connection1);
        $this->server->onOpen($connection2);
        
        $subscribeMessage = json_encode([
            'type' => 'subscribe',
            'channel' => 'notifications.1',
        ]);
        
        $this->server->onMessage($connection1, $subscribeMessage);
        $this->server->onMessage($connection2, $subscribeMessage);
        
        // Mock message tracking
        $sentMessages = [];
        $connection1->send = function($data) use (&$sentMessages) {
            $sentMessages[] = $data;
        };
        $connection2->send = function($data) use (&$sentMessages) {
            $sentMessages[] = $data;
        };
        
        // Broadcast message
        $broadcastData = [
            'type' => 'notification',
            'title' => 'Test Notification',
            'message' => 'This is a test message',
        ];
        
        $this->server->broadcastToChannel('notifications.1', $broadcastData);
        
        // Both connections should receive the message
        $this->assertCount(2, $sentMessages);
    }

    /** @test */
    public function it_handles_connection_errors_gracefully()
    {
        $connection = $this->createMockWebSocketConnection();
        
        $this->server->onOpen($connection);
        
        // Simulate error
        $exception = new \Exception('Test error');
        $this->server->onError($connection, $exception);
        
        // Connection should be removed after error
        $this->assertFalse($this->connectionManager->hasConnection($connection->resourceId));
    }

    /** @test */
    public function it_validates_message_format()
    {
        $connection = $this->createMockWebSocketConnection();
        $token = $this->createJwtToken(['user_id' => 1, 'name' => 'Test User']);
        $connection->httpHeaders = ['Authorization' => 'Bearer ' . $token];
        
        $this->server->onOpen($connection);
        
        // Send invalid JSON
        $this->server->onMessage($connection, 'invalid-json');
        
        // Send message without required fields
        $invalidMessage = json_encode(['incomplete' => 'data']);
        $this->server->onMessage($connection, $invalidMessage);
        
        // Connection should remain open but no actions should be taken
        $this->assertTrue($this->connectionManager->hasConnection($connection->resourceId));
    }

    /** @test */
    public function it_tracks_connection_statistics()
    {
        $connections = [];
        
        // Create multiple connections
        for ($i = 1; $i <= 3; $i++) {
            $connection = $this->createMockWebSocketConnection();
            $connection->resourceId = $i;
            $connections[] = $connection;
            
            $this->server->onOpen($connection);
        }
        
        // Verify statistics
        $stats = $this->connectionManager->getStats();
        $this->assertEquals(3, $stats['total_connections']);
        $this->assertEquals(0, $stats['authenticated_connections']); // No auth tokens provided
        
        // Close one connection
        $this->server->onClose($connections[0]);
        
        $stats = $this->connectionManager->getStats();
        $this->assertEquals(2, $stats['total_connections']);
    }
}