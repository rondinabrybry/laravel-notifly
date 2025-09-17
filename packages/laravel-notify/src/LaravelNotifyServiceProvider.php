<?php

namespace LaravelNotify;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;
use LaravelNotify\Broadcasting\WebSocketBroadcaster;
use LaravelNotify\Console\Commands\WebSocketStartCommand;
use LaravelNotify\Server\WebSocketServer;

class LaravelNotifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/realtime.php',
            'realtime'
        );

        $this->app->singleton(WebSocketServer::class, function ($app) {
            return new WebSocketServer($app['config']['realtime']);
        });

        $this->app->singleton('websocket.broadcaster', function ($app) {
            return new WebSocketBroadcaster($app[WebSocketServer::class]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/realtime.php' => config_path('realtime.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/laravel-notify/js'),
        ], 'js');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-notify'),
        ], 'views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-notify');

        if ($this->app->runningInConsole()) {
            $this->commands([
                WebSocketStartCommand::class,
            ]);
        }

        $this->extendBroadcastManager();
    }

    /**
     * Extend the Laravel broadcast manager with WebSocket broadcaster.
     */
    protected function extendBroadcastManager(): void
    {
        $this->app->extend('broadcast', function (BroadcastManager $manager, $app) {
            $manager->extend('websocket', function ($app, $config) {
                return $app['websocket.broadcaster'];
            });

            return $manager;
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            WebSocketServer::class,
            'websocket.broadcaster',
        ];
    }
}