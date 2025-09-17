<?php

namespace LaravelNotify\Console\Commands;

use Illuminate\Console\Command;

class WebSocketStopCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:stop {--force : Force stop without graceful shutdown}';

    /**
     * The console command description.
     */
    protected $description = 'Stop the WebSocket server daemon';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pidFile = storage_path('app/websocket.pid');

        if (!file_exists($pidFile)) {
            $this->error('WebSocket server is not running (no PID file found)');
            return Command::FAILURE;
        }

        $pid = (int) file_get_contents($pidFile);

        if (!$this->isProcessRunning($pid)) {
            $this->warn('WebSocket server PID file exists but process is not running');
            unlink($pidFile);
            return Command::SUCCESS;
        }

        $force = $this->option('force');

        if ($force) {
            // Force kill
            if (posix_kill($pid, SIGKILL)) {
                $this->info("WebSocket server (PID: {$pid}) forcefully stopped");
            } else {
                $this->error("Failed to stop WebSocket server (PID: {$pid})");
                return Command::FAILURE;
            }
        } else {
            // Graceful shutdown
            if (posix_kill($pid, SIGTERM)) {
                $this->info("Sending shutdown signal to WebSocket server (PID: {$pid})");
                
                // Wait for graceful shutdown
                $timeout = 30; // seconds
                $start = time();
                
                while ($this->isProcessRunning($pid) && (time() - $start) < $timeout) {
                    sleep(1);
                    $this->output->write('.');
                }
                
                if ($this->isProcessRunning($pid)) {
                    $this->warn("\nGraceful shutdown timed out, sending KILL signal");
                    posix_kill($pid, SIGKILL);
                } else {
                    $this->info("\nWebSocket server stopped gracefully");
                }
            } else {
                $this->error("Failed to send shutdown signal to WebSocket server (PID: {$pid})");
                return Command::FAILURE;
            }
        }

        // Clean up PID file
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a process is running
     */
    protected function isProcessRunning(int $pid): bool
    {
        return posix_kill($pid, 0);
    }
}

class WebSocketRestartCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:restart {--daemon : Restart in daemon mode}';

    /**
     * The console command description.
     */
    protected $description = 'Restart the WebSocket server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Restarting WebSocket server...');

        // Stop the server
        $stopResult = $this->call('websocket:stop');
        
        if ($stopResult !== Command::SUCCESS) {
            $this->error('Failed to stop WebSocket server');
            return Command::FAILURE;
        }

        // Wait a moment
        sleep(2);

        // Start the server
        $startOptions = [];
        if ($this->option('daemon')) {
            $startOptions['--daemon'] = true;
        }

        $startResult = $this->call('websocket:start', $startOptions);

        if ($startResult === Command::SUCCESS) {
            $this->info('WebSocket server restarted successfully');
        } else {
            $this->error('Failed to start WebSocket server');
        }

        return $startResult;
    }
}

class WebSocketClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:clients {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'List connected WebSocket clients';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $connectionState = app(\LaravelNotify\Contracts\ConnectionStateInterface::class);
            $stats = $connectionState->getServerStats();
            $format = $this->option('format');

            if ($format === 'json') {
                $this->line(json_encode($stats['connections_by_cluster'], JSON_PRETTY_PRINT));
            } else {
                $this->displayClientsTable($stats);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get client list: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display clients in table format
     */
    protected function displayClientsTable(array $stats): void
    {
        $this->info('Connected WebSocket Clients');
        $this->line('');

        if ($stats['total_connections'] === 0) {
            $this->warn('No clients connected');
            return;
        }

        $this->table(
            ['Cluster', 'Connections'],
            array_map(
                fn($cluster, $count) => [$cluster, $count],
                array_keys($stats['connections_by_cluster']),
                $stats['connections_by_cluster']
            )
        );

        $this->line('');
        $this->info("Total: {$stats['total_connections']} connections");
    }
}

class WebSocketChannelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:channels {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Show WebSocket channel statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $connectionState = app(\LaravelNotify\Contracts\ConnectionStateInterface::class);
            $stats = $connectionState->getServerStats();
            $format = $this->option('format');

            if ($format === 'json') {
                $this->line(json_encode([
                    'active_channels' => $stats['active_channels'],
                    'channels' => $stats['channels'] ?? [],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->displayChannelsTable($stats);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get channel statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display channels in table format
     */
    protected function displayChannelsTable(array $stats): void
    {
        $this->info('WebSocket Channel Statistics');
        $this->line('');

        if ($stats['active_channels'] === 0) {
            $this->warn('No active channels');
            return;
        }

        if (!empty($stats['channels'])) {
            $channelData = [];
            foreach ($stats['channels'] as $channel => $subscriberCount) {
                $channelData[] = [$channel, $subscriberCount];
            }

            $this->table(['Channel', 'Subscribers'], $channelData);
        }

        $this->line('');
        $this->info("Total active channels: {$stats['active_channels']}");
    }
}