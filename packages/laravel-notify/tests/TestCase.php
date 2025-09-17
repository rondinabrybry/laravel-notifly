<?php

namespace LaravelNotify\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use LaravelNotify\LaravelNotifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load package migrations
        $this->loadLaravelMigrations();
        
        // Set up test environment
        $this->setUpEnvironment();
        
        // Configure test database
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelNotifyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Configure database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure cache
        $app['config']->set('cache.default', 'array');
        
        // Configure session
        $app['config']->set('session.driver', 'array');

        // Configure realtime settings
        $app['config']->set('realtime.websocket.host', '127.0.0.1');
        $app['config']->set('realtime.websocket.port', 8080);
        $app['config']->set('realtime.redis.enabled', false);
        $app['config']->set('realtime.authentication.provider', 'jwt');
        $app['config']->set('realtime.rate_limiting.enabled', false);
        $app['config']->set('realtime.metrics.enabled', false);
    }

    protected function setUpEnvironment(): void
    {
        // Set JWT secret for testing
        config(['realtime.authentication.jwt.secret' => 'test-secret-key-for-jwt-testing']);
        
        // Disable logging errors during tests
        config(['logging.default' => 'null']);
    }

    protected function setUpDatabase(): void
    {
        // Create users table for authentication tests
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Create test user
     */
    protected function createTestUser(array $attributes = []): \Illuminate\Foundation\Auth\User
    {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';
            protected $fillable = ['name', 'email', 'password'];
        };

        return $user->create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    /**
     * Create JWT token for testing
     */
    protected function createJwtToken(array $payload = []): string
    {
        $jwtProvider = new \LaravelNotify\Authentication\Providers\JWTAuthProvider([
            'secret' => 'test-secret-key-for-jwt-testing',
            'algorithm' => 'HS256',
            'expires_in' => 3600,
        ]);

        $defaultPayload = [
            'user_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        return $jwtProvider->generateToken(array_merge($defaultPayload, $payload));
    }

    /**
     * Assert WebSocket connection is authenticated
     */
    protected function assertWebSocketAuthenticated(array $authData): void
    {
        $this->assertIsArray($authData);
        $this->assertArrayHasKey('id', $authData);
        $this->assertArrayHasKey('name', $authData);
        $this->assertArrayHasKey('provider', $authData);
    }

    /**
     * Assert WebSocket connection is not authenticated
     */
    protected function assertWebSocketNotAuthenticated($authData): void
    {
        $this->assertNull($authData);
    }

    /**
     * Mock Redis connection for testing
     */
    protected function mockRedis(): void
    {
        $this->app->bind('redis', function() {
            return new class {
                public function connection($name = null) {
                    return new class {
                        private array $data = [];
                        
                        public function set($key, $value, $options = null) {
                            $this->data[$key] = $value;
                            return 'OK';
                        }
                        
                        public function get($key) {
                            return $this->data[$key] ?? null;
                        }
                        
                        public function del($key) {
                            unset($this->data[$key]);
                            return 1;
                        }
                        
                        public function exists($key) {
                            return isset($this->data[$key]);
                        }
                        
                        public function incr($key) {
                            $this->data[$key] = ($this->data[$key] ?? 0) + 1;
                            return $this->data[$key];
                        }
                        
                        public function expire($key, $seconds) {
                            return true;
                        }
                        
                        public function ping() {
                            return 'PONG';
                        }
                    };
                }
            };
        });
    }

    /**
     * Create mock WebSocket connection
     */
    protected function createMockWebSocketConnection(): object
    {
        return new class {
            public $resourceId = 1;
            public $remoteAddress = '127.0.0.1';
            public array $httpHeaders = [];
            public array $WebSocket = ['masked' => true];
            
            public function send($data) {
                // Mock sending data
                return true;
            }
            
            public function close() {
                // Mock closing connection
                return true;
            }
        };
    }
}