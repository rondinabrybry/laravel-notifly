<?php

namespace LaravelNotify\Console\Commands;

use Illuminate\Console\Command;
use LaravelNotify\Contracts\ConnectionStateInterface;

class WebSocketStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:status {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Show WebSocket server status and statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $connectionState = app(ConnectionStateInterface::class);
            $stats = $connectionState->getServerStats();
            $format = $this->option('format');

            if ($format === 'json') {
                $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            } else {
                $this->displayTableFormat($stats);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get server status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display statistics in table format
     */
    protected function displayTableFormat(array $stats): void
    {
        $this->info('WebSocket Server Status');
        $this->line('');

        // General statistics
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Connections', $stats['total_connections']],
                ['Active Channels', $stats['active_channels']],
                ['Memory Usage', $this->formatBytes($stats['memory_usage'])],
                ['Uptime', $this->formatUptime($stats['uptime'])],
            ]
        );

        // Connections by cluster
        if (!empty($stats['connections_by_cluster'])) {
            $this->line('');
            $this->info('Connections by Cluster:');
            $clusterData = [];
            foreach ($stats['connections_by_cluster'] as $cluster => $count) {
                $clusterData[] = [$cluster, $count];
            }
            $this->table(['Cluster ID', 'Connections'], $clusterData);
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Format uptime to human readable format
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%dd %02dh %02dm %02ds', $days, $hours, $minutes, $secs);
    }
}