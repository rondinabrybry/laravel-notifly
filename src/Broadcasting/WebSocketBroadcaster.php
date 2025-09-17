<?php

namespace LaravelNotify\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use LaravelNotify\Server\WebSocketServer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebSocketBroadcaster extends Broadcaster
{
    protected WebSocketServer $server;

    public function __construct(WebSocketServer $server)
    {
        $this->server = $server;
    }

    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth($request, array $channels)
    {
        return parent::verifyUserCanAccessChannel(
            $request,
            $channels[0]
        );
    }

    /**
     * Return the valid authentication response.
     */
    public function validAuthenticationResponse($request, $result)
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        return json_encode(['channel_data' => [
            'user_id' => $request->user()->id,
            'user_info' => $result,
        ]]);
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $message = [
            'event' => $event,
            'data' => $payload,
            'channels' => $channels,
            'timestamp' => time(),
        ];

        foreach ($channels as $channel) {
            $this->server->broadcastToChannel($channel, $message);
        }
    }

    /**
     * Get the socket ID for the given request.
     */
    public function getSocketId($request)
    {
        return $request->header('X-Socket-ID');
    }
}