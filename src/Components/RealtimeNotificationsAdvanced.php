<?php

namespace LaravelNotify\Components;

use Illuminate\View\Component;

class RealtimeNotificationsAdvanced extends Component
{
    public string $theme;
    public array $config;
    public bool $showAvatar;
    public bool $enableSound;
    public string $position;
    public int $maxNotifications;
    public bool $autoClose;
    public int $autoCloseDelay;
    public bool $enablePersistence;
    public string $containerClass;

    public function __construct(
        string $theme = 'default',
        array $config = [],
        bool $showAvatar = true,
        bool $enableSound = true,
        string $position = 'top-right',
        int $maxNotifications = 5,
        bool $autoClose = true,
        int $autoCloseDelay = 5000,
        bool $enablePersistence = false,
        string $containerClass = ''
    ) {
        $this->theme = $theme;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->showAvatar = $showAvatar;
        $this->enableSound = $enableSound;
        $this->position = $position;
        $this->maxNotifications = $maxNotifications;
        $this->autoClose = $autoClose;
        $this->autoCloseDelay = $autoCloseDelay;
        $this->enablePersistence = $enablePersistence;
        $this->containerClass = $containerClass;
    }

    public function render()
    {
        return view('laravel-notify::components.realtime-notifications-advanced');
    }

    protected function getDefaultConfig(): array
    {
        return [
            'websocket_url' => config('realtime.websocket.url', 'ws://localhost:8080'),
            'authentication_token' => null,
            'channels' => ['notifications.' . (auth()->id() ?? 'guest')],
            'reconnect_attempts' => 5,
            'reconnect_delay' => 3000,
            'heartbeat_interval' => 30000,
            'enable_debug' => false,
        ];
    }

    public function getThemeClasses(): string
    {
        $themes = [
            'default' => 'bg-white border-gray-200 text-gray-800',
            'dark' => 'bg-gray-800 border-gray-700 text-white',
            'success' => 'bg-green-50 border-green-200 text-green-800',
            'info' => 'bg-blue-50 border-blue-200 text-blue-800',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
            'error' => 'bg-red-50 border-red-200 text-red-800',
            'minimal' => 'bg-white border-transparent text-gray-600 shadow-lg',
            'material' => 'bg-white text-gray-800 shadow-md rounded-lg',
            'glass' => 'bg-white bg-opacity-20 backdrop-blur-lg border-white border-opacity-20',
        ];

        return $themes[$this->theme] ?? $themes['default'];
    }

    public function getPositionClasses(): string
    {
        $positions = [
            'top-left' => 'top-4 left-4',
            'top-center' => 'top-4 left-1/2 transform -translate-x-1/2',
            'top-right' => 'top-4 right-4',
            'bottom-left' => 'bottom-4 left-4',
            'bottom-center' => 'bottom-4 left-1/2 transform -translate-x-1/2',
            'bottom-right' => 'bottom-4 right-4',
            'center' => 'top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2',
        ];

        return $positions[$this->position] ?? $positions['top-right'];
    }

    public function getAnimationConfig(): array
    {
        return [
            'enter' => 'transition ease-out duration-300 transform opacity-0 scale-95',
            'enterTo' => 'opacity-100 scale-100',
            'leave' => 'transition ease-in duration-200 transform opacity-100 scale-100',
            'leaveTo' => 'opacity-0 scale-95',
        ];
    }

    public function getSoundConfig(): array
    {
        if (!$this->enableSound) {
            return [];
        }

        return [
            'notification' => asset('vendor/laravel-notify/sounds/notification.mp3'),
            'success' => asset('vendor/laravel-notify/sounds/success.mp3'),
            'warning' => asset('vendor/laravel-notify/sounds/warning.mp3'),
            'error' => asset('vendor/laravel-notify/sounds/error.mp3'),
        ];
    }
}