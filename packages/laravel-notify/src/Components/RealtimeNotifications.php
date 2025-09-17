<?php

namespace LaravelNotify\Components;

use Illuminate\View\Component;

class RealtimeNotifications extends Component
{
    public $userId;
    public $channels;
    public $wsUrl;

    /**
     * Create a new component instance.
     */
    public function __construct($userId = null, $channels = ['notifications'], $wsUrl = null)
    {
        $this->userId = $userId ?? auth()->id();
        $this->channels = is_array($channels) ? $channels : [$channels];
        $this->wsUrl = $wsUrl ?? $this->getWebSocketUrl();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('laravel-notify::components.realtime-notifications');
    }

    /**
     * Get WebSocket URL from config
     */
    protected function getWebSocketUrl(): string
    {
        $config = config('realtime.server');
        $protocol = config('realtime.ssl.enabled') ? 'wss' : 'ws';
        
        return "{$protocol}://{$config['host']}:{$config['port']}";
    }
}