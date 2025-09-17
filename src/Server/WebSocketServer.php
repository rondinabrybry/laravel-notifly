<?php

namespace LaravelNotify\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use LaravelNotify\Server\ConnectionManager;
use LaravelNotify\Server\AuthenticationHandler;

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $config;
    protected $connectionManager;
    protected $authHandler;

    public function __construct(array $config)
    {
        $this->clients = new \SplObjectStorage;
        $this->config = $config;
        $this->connectionManager = new ConnectionManager();
        $this->authHandler = new AuthenticationHandler($config['auth']);
    }

    /**
     * Start the WebSocket server
     */
    public function start(): void
    {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $this->config['server']['port'],
            $this->config['server']['host']
        );

        echo "WebSocket server starting on {$this->config['server']['host']}:{$this->config['server']['port']}\n";
        
        $server->run();
    }

    /**
     * Handle new connection
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->connectionManager->addConnection($conn);
        
        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * Handle incoming messages
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        
        if (!$data) {
            $this->sendError($from, 'Invalid JSON format');
            return;
        }

        $this->handleMessage($from, $data);
    }

    /**
     * Handle connection close
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $this->connectionManager->removeConnection($conn);
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * Handle connection error
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Handle different types of messages
     */
    protected function handleMessage(ConnectionInterface $from, array $data): void
    {
        $type = $data['type'] ?? null;

        switch ($type) {
            case 'auth':
                $this->handleAuthentication($from, $data);
                break;
            
            case 'subscribe':
                $this->handleSubscription($from, $data);
                break;
            
            case 'unsubscribe':
                $this->handleUnsubscription($from, $data);
                break;
            
            case 'message':
                $this->handleBroadcastMessage($from, $data);
                break;
            
            default:
                $this->sendError($from, 'Unknown message type');
        }
    }

    /**
     * Handle authentication
     */
    protected function handleAuthentication(ConnectionInterface $conn, array $data): void
    {
        $token = $data['token'] ?? null;
        
        if (!$token) {
            $this->sendError($conn, 'Authentication token required');
            return;
        }

        $user = $this->authHandler->authenticate($token);
        
        if ($user) {
            $this->connectionManager->authenticateConnection($conn, $user);
            $this->sendResponse($conn, 'auth', ['status' => 'authenticated', 'user' => $user]);
        } else {
            $this->sendError($conn, 'Authentication failed');
        }
    }

    /**
     * Handle channel subscription
     */
    protected function handleSubscription(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        
        if (!$channel) {
            $this->sendError($conn, 'Channel name required');
            return;
        }

        if (!$this->connectionManager->isAuthenticated($conn) && $this->isPrivateChannel($channel)) {
            $this->sendError($conn, 'Authentication required for private channels');
            return;
        }

        $this->connectionManager->subscribeToChannel($conn, $channel);
        $this->sendResponse($conn, 'subscribe', ['channel' => $channel, 'status' => 'subscribed']);
    }

    /**
     * Handle channel unsubscription
     */
    protected function handleUnsubscription(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        
        if (!$channel) {
            $this->sendError($conn, 'Channel name required');
            return;
        }

        $this->connectionManager->unsubscribeFromChannel($conn, $channel);
        $this->sendResponse($conn, 'unsubscribe', ['channel' => $channel, 'status' => 'unsubscribed']);
    }

    /**
     * Handle broadcast message
     */
    protected function handleBroadcastMessage(ConnectionInterface $from, array $data): void
    {
        $channel = $data['channel'] ?? null;
        $message = $data['message'] ?? null;

        if (!$channel || !$message) {
            $this->sendError($from, 'Channel and message required');
            return;
        }

        $this->broadcastToChannel($channel, $message, $from);
    }

    /**
     * Broadcast message to all subscribers of a channel
     */
    public function broadcastToChannel(string $channel, array $message, ConnectionInterface $exclude = null): void
    {
        $subscribers = $this->connectionManager->getChannelSubscribers($channel);
        
        foreach ($subscribers as $conn) {
            if ($exclude && $conn === $exclude) {
                continue;
            }
            
            $this->sendResponse($conn, 'broadcast', [
                'channel' => $channel,
                'message' => $message
            ]);
        }
    }

    /**
     * Send response to connection
     */
    protected function sendResponse(ConnectionInterface $conn, string $type, array $data): void
    {
        $response = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ];
        
        $conn->send(json_encode($response));
    }

    /**
     * Send error to connection
     */
    protected function sendError(ConnectionInterface $conn, string $message): void
    {
        $this->sendResponse($conn, 'error', ['message' => $message]);
    }

    /**
     * Check if channel is private
     */
    protected function isPrivateChannel(string $channel): bool
    {
        $privateChannels = $this->config['broadcasting']['private_channels'] ?? [];
        
        foreach ($privateChannels as $pattern) {
            if (fnmatch($pattern, $channel)) {
                return true;
            }
        }
        
        return false;
    }
}