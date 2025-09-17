<?php

namespace LaravelNotify\Listeners;

use LaravelNotify\Events\NewMessageEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMessageNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NewMessageEvent $event): void
    {
        // Log the message
        \Log::info('New message received', [
            'message' => $event->message,
            'user' => $event->user,
            'channel' => $event->channel,
        ]);

        // Here you can add additional processing like:
        // - Save message to database
        // - Send email notifications
        // - Update user notification count
        // - Push notifications to mobile devices
        // - etc.
        
        // Example: Save to database (uncomment if you have a Message model)
        // Message::create([
        //     'content' => $event->message,
        //     'user_id' => $event->user['id'] ?? null,
        //     'channel' => $event->channel,
        // ]);
    }
}