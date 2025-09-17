<?php

namespace LaravelNotify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelNotify\Contracts\ConnectionStateInterface;

class WebSocketHealthController
{
    protected ConnectionStateInterface $connectionState;
    protected array $config;

    public function __construct(ConnectionStateInterface $connectionState)
    {
        $this->connectionState = $connectionState;
        $this->config = config('realtime.health_check', []);
    }

    /**
     * Health check endpoint
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $checks = $this->performHealthChecks();
            $isHealthy = $this->isSystemHealthy($checks);

            return response()->json([
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'checks' => $checks,
            ], $isHealthy ? 200 : 503);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Detailed status endpoint
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $stats = $this->connectionState->getServerStats();
            $checks = $this->performHealthChecks();

            return response()->json([
                'status' => $this->isSystemHealthy($checks) ? 'healthy' : 'unhealthy',
                'statistics' => $stats,
                'health_checks' => $checks,
                'server_info' => [
                    'hostname' => gethostname(),
                    'php_version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Metrics endpoint for Prometheus
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $metricsExporter = app(\LaravelNotify\Metrics\MetricsExporter::class);
            $prometheusData = $metricsExporter->exportToPrometheus();

            return response($prometheusData, 200, [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Metrics export failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Perform all health checks
     */
    protected function performHealthChecks(): array
    {
        $checks = [];

        // Connection count check
        if ($this->config['checks']['connection_count'] ?? true) {
            $checks['connection_count'] = $this->checkConnectionCount();
        }

        // Memory usage check
        if ($this->config['checks']['memory_usage'] ?? true) {
            $checks['memory_usage'] = $this->checkMemoryUsage();
        }

        // Redis connection check
        if ($this->config['checks']['redis_connection'] ?? true) {
            $checks['redis_connection'] = $this->checkRedisConnection();
        }

        // Database connection check
        if ($this->config['checks']['database_connection'] ?? false) {
            $checks['database_connection'] = $this->checkDatabaseConnection();
        }

        return $checks;
    }

    /**
     * Check connection count
     */
    protected function checkConnectionCount(): array
    {
        $stats = $this->connectionState->getServerStats();
        $currentConnections = $stats['total_connections'];
        $maxConnections = $this->config['thresholds']['max_connections'] ?? 900;

        return [
            'status' => $currentConnections <= $maxConnections ? 'pass' : 'fail',
            'current' => $currentConnections,
            'threshold' => $maxConnections,
            'message' => $currentConnections <= $maxConnections 
                ? 'Connection count is within limits' 
                : 'Connection count exceeds threshold',
        ];
    }

    /**
     * Check memory usage
     */
    protected function checkMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryUsageMB = round($memoryUsage / 1024 / 1024, 2);
        $maxMemoryMB = $this->config['thresholds']['max_memory_mb'] ?? 512;

        return [
            'status' => $memoryUsageMB <= $maxMemoryMB ? 'pass' : 'fail',
            'current_mb' => $memoryUsageMB,
            'threshold_mb' => $maxMemoryMB,
            'current_bytes' => $memoryUsage,
            'message' => $memoryUsageMB <= $maxMemoryMB 
                ? 'Memory usage is within limits' 
                : 'Memory usage exceeds threshold',
        ];
    }

    /**
     * Check Redis connection
     */
    protected function checkRedisConnection(): array
    {
        try {
            if (config('realtime.redis.enabled')) {
                $redis = \Illuminate\Support\Facades\Redis::connection(
                    config('realtime.redis.connection', 'default')
                );
                $redis->ping();
                
                return [
                    'status' => 'pass',
                    'message' => 'Redis connection is healthy',
                ];
            } else {
                return [
                    'status' => 'skip',
                    'message' => 'Redis is not enabled',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'message' => 'Redis connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check database connection
     */
    protected function checkDatabaseConnection(): array
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            
            return [
                'status' => 'pass',
                'message' => 'Database connection is healthy',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'fail',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine if system is healthy based on checks
     */
    protected function isSystemHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }
}