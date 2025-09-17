<?php

namespace LaravelNotify\Console\Commands;

use Illuminate\Console\Command;
use LaravelNotify\Server\WebSocketServer;

class WebSocketStartCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:start {--daemon : Run the server in daemon mode}';

    /**
     * The console command description.
     */
    protected $description = 'Start the WebSocket server for real-time notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting WebSocket Server...');
        
        $config = config('realtime');
        $server = new WebSocketServer($config);

        $this->info("WebSocket server will start on {$config['server']['host']}:{$config['server']['port']}");
        
        if ($this->option('daemon')) {
            $this->info('Running in daemon mode...');
            $this->runDaemon($server);
        } else {
            $this->info('Running in foreground mode (use Ctrl+C to stop)...');
            $server->start();
        }

        return Command::SUCCESS;
    }

    /**
     * Run the server in daemon mode
     */
    protected function runDaemon(WebSocketServer $server): void
    {
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            $this->error('Could not fork process');
            return;
        }
        
        if ($pid) {
            // Parent process - exit
            $this->info("WebSocket server started with PID: {$pid}");
            file_put_contents(storage_path('app/websocket.pid'), $pid);
            exit(0);
        }
        
        // Child process - start server
        $server->start();
    }
}