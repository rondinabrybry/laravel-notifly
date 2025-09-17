<?php

namespace LaravelNotify\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $user;
    public $channel;

    /**
     * Create a new event instance.
     */
    public function __construct($message, $user = null, $channel = 'notifications')
    {
        $this->message = $message;
        $this->user = $user;
        $this->channel = $channel;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        if ($this->user) {
            // Send to specific user
            return [
                new PrivateChannel('user.' . $this->user['id'])
            ];
        }

        // Broadcast to public channel
        return [
            new Channel($this->channel)
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'user' => $this->user,
            'timestamp' => now()->toISOString(),
        ];
    }
}