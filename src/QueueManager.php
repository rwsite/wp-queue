<?php

declare(strict_types=1);

namespace WPQueue;

use InvalidArgumentException;
use WPQueue\Contracts\QueueInterface;
use WPQueue\Queue\DatabaseQueue;
use WPQueue\Queue\MemcachedQueue;
use WPQueue\Queue\RedisQueue;
use WPQueue\Queue\SyncQueue;

/**
 * Queue Manager - manages queue connections and drivers.
 *
 * Supported drivers:
 * - database: Uses wp_options (default)
 * - sync: Synchronous execution (no queue)
 * - redis: Redis server (requires phpredis extension)
 * - memcached: Memcached server (requires memcached extension)
 * - auto: Automatically selects best available driver
 *
 * Configuration via wp-config.php constants:
 *
 * Redis (compatible with redis-cache plugin):
 * - WP_REDIS_HOST, WP_REDIS_PORT, WP_REDIS_PASSWORD, WP_REDIS_DATABASE
 * - WP_REDIS_PREFIX, WP_REDIS_SCHEME, WP_REDIS_PATH
 *
 * Memcached:
 * - WP_MEMCACHED_HOST, WP_MEMCACHED_PORT, WP_MEMCACHED_PREFIX
 *
 * Queue driver selection:
 * - WP_QUEUE_DRIVER: 'database', 'sync', 'redis', 'memcached', 'auto'
 */
class QueueManager
{
    /**
     * Driver status constants.
     */
    public const STATUS_READY = 'ready';

    public const STATUS_NO_EXTENSION = 'no_extension';

    public const STATUS_NO_SERVER = 'no_server';

    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * @var array<string, QueueInterface>
     */
    protected array $connections = [];

    /**
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    protected ?string $defaultDriver = null;

    /**
     * Cached driver status info.
     *
     * @var array<string, array{status: string, extension: bool, server: bool, message: string}>|null
     */
    protected ?array $driverStatusCache = null;

    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->getDefaultDriver();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    /**
     * Get the default driver name.
     *
     * Priority:
     * 1. Explicitly set driver via setDefaultDriver()
     * 2. WP_QUEUE_DRIVER constant (with availability check)
     * 3. 'database' as fallback
     *
     * IMPORTANT: If configured driver is not available, falls back to 'database'
     * to prevent fatal errors.
     */
    public function getDefaultDriver(): string
    {
        if ($this->defaultDriver !== null) {
            // Even explicitly set driver must be available
            if ($this->isDriverReady($this->defaultDriver)) {
                return $this->defaultDriver;
            }

            return 'database';
        }

        // Check WP_QUEUE_DRIVER constant
        if (defined('WP_QUEUE_DRIVER')) {
            $driver = WP_QUEUE_DRIVER;

            if ($driver === 'auto') {
                return $this->detectBestDriver();
            }

            // Validate that configured driver is actually ready
            if ($this->isDriverReady($driver)) {
                return $driver;
            }

            // Log warning about fallback (only once per request)
            static $warned = [];
            if (! isset($warned[$driver]) && function_exists('error_log')) {
                error_log(sprintf(
                    '[WP Queue] Driver "%s" is configured but not available. Falling back to "database". Run: wp queue drivers',
                    $driver,
                ));
                $warned[$driver] = true;
            }

            return 'database';
        }

        return 'database';
    }

    /**
     * Get the configured driver name (without fallback).
     *
     * Use this to show what user configured, even if it's not available.
     */
    public function getConfiguredDriver(): string
    {
        if ($this->defaultDriver !== null) {
            return $this->defaultDriver;
        }

        if (defined('WP_QUEUE_DRIVER')) {
            return WP_QUEUE_DRIVER;
        }

        return 'database';
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Detect the best available driver.
     *
     * Priority: redis > memcached > database
     */
    public function detectBestDriver(): string
    {
        if ($this->isDriverReady('redis')) {
            return 'redis';
        }

        if ($this->isDriverReady('memcached')) {
            return 'memcached';
        }

        return 'database';
    }

    /**
     * Check if a driver is fully ready to use.
     *
     * This checks both extension availability AND server connectivity.
     */
    public function isDriverReady(string $driver): bool
    {
        $status = $this->getDriverStatus($driver);

        return $status['status'] === self::STATUS_READY;
    }

    /**
     * Get detailed driver status.
     *
     * @return array{status: string, extension: bool, server: bool, message: string}
     */
    public function getDriverStatus(string $driver): array
    {
        // Return cached status if available
        if (isset($this->driverStatusCache[$driver])) {
            return $this->driverStatusCache[$driver];
        }

        $status = match ($driver) {
            'database', 'sync' => [
                'status' => self::STATUS_READY,
                'extension' => true,
                'server' => true,
                'message' => $driver === 'database'
                    ? __('Uses wp_options table', 'wp-queue')
                    : __('Synchronous execution (no queue)', 'wp-queue'),
            ],
            'redis' => $this->getRedisStatus(),
            'memcached' => $this->getMemcachedStatus(),
            default => isset($this->customCreators[$driver])
                ? [
                    'status' => self::STATUS_READY,
                    'extension' => true,
                    'server' => true,
                    'message' => __('Custom driver', 'wp-queue'),
                ]
                : [
                    'status' => self::STATUS_UNAVAILABLE,
                    'extension' => false,
                    'server' => false,
                    'message' => __('Unknown driver', 'wp-queue'),
                ],
        };

        $this->driverStatusCache[$driver] = $status;

        return $status;
    }

    /**
     * Get Redis driver status with detailed checks.
     *
     * @return array{status: string, extension: bool, server: bool, message: string}
     */
    protected function getRedisStatus(): array
    {
        // Step 1: Check PHP extension
        if (! extension_loaded('redis')) {
            return [
                'status' => self::STATUS_NO_EXTENSION,
                'extension' => false,
                'server' => false,
                'message' => __('PHP extension "redis" (phpredis) is not installed', 'wp-queue'),
            ];
        }

        // Step 2: Check server connectivity
        try {
            $queue = new RedisQueue();
            // Use reflection to call protected connection() method for testing
            $reflection = new \ReflectionMethod($queue, 'connection');
            $reflection->setAccessible(true);
            $redis = $reflection->invoke($queue);
            $redis->ping();

            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;

            return [
                'status' => self::STATUS_READY,
                'extension' => true,
                'server' => true,
                'message' => sprintf(__('Connected to Redis at %s:%d', 'wp-queue'), $host, $port),
            ];
        } catch (\Throwable $e) {
            $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;

            return [
                'status' => self::STATUS_NO_SERVER,
                'extension' => true,
                'server' => false,
                'message' => sprintf(
                    __('Cannot connect to Redis at %s:%d - %s', 'wp-queue'),
                    $host,
                    $port,
                    $e->getMessage(),
                ),
            ];
        }
    }

    /**
     * Get Memcached driver status with detailed checks.
     *
     * @return array{status: string, extension: bool, server: bool, message: string}
     */
    protected function getMemcachedStatus(): array
    {
        // Step 1: Check PHP extension
        if (! extension_loaded('memcached')) {
            return [
                'status' => self::STATUS_NO_EXTENSION,
                'extension' => false,
                'server' => false,
                'message' => __('PHP extension "memcached" is not installed', 'wp-queue'),
            ];
        }

        // Step 2: Check server connectivity
        try {
            $queue = new MemcachedQueue();
            // Use reflection to call protected connection() method for testing
            $reflection = new \ReflectionMethod($queue, 'connection');
            $reflection->setAccessible(true);
            $reflection->invoke($queue);

            $host = defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1';
            $port = defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211;

            return [
                'status' => self::STATUS_READY,
                'extension' => true,
                'server' => true,
                'message' => sprintf(__('Connected to Memcached at %s:%d', 'wp-queue'), $host, $port),
            ];
        } catch (\Throwable $e) {
            $host = defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1';
            $port = defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211;

            return [
                'status' => self::STATUS_NO_SERVER,
                'extension' => true,
                'server' => false,
                'message' => sprintf(
                    __('Cannot connect to Memcached at %s:%d - %s', 'wp-queue'),
                    $host,
                    $port,
                    $e->getMessage(),
                ),
            ];
        }
    }

    /**
     * Get all available drivers with detailed status.
     *
     * @return array<string, array{status: string, extension: bool, server: bool, message: string, available: bool}>
     */
    public function getAvailableDrivers(): array
    {
        $drivers = [];

        foreach (['database', 'sync', 'redis', 'memcached'] as $driver) {
            $status = $this->getDriverStatus($driver);
            $drivers[$driver] = array_merge($status, [
                'available' => $status['status'] === self::STATUS_READY,
                // Legacy compatibility
                'info' => $status['message'],
            ]);
        }

        // Add custom drivers
        foreach (array_keys($this->customCreators) as $name) {
            $drivers[$name] = [
                'status' => self::STATUS_READY,
                'extension' => true,
                'server' => true,
                'message' => __('Custom driver', 'wp-queue'),
                'available' => true,
                'info' => __('Custom driver', 'wp-queue'),
            ];
        }

        return $drivers;
    }

    /**
     * Check if a driver is available (legacy method).
     *
     * @deprecated Use isDriverReady() for accurate status
     */
    public function isDriverAvailable(string $driver): bool
    {
        return $this->isDriverReady($driver);
    }

    /**
     * @param  callable(QueueManager): QueueInterface  $callback
     */
    public function extend(string $driver, callable $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    protected function resolve(string $name): QueueInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this);
        }

        return match ($name) {
            'database' => new DatabaseQueue(),
            'sync' => new SyncQueue(),
            'redis' => new RedisQueue(),
            'memcached' => new MemcachedQueue(),
            'auto' => $this->resolve($this->detectBestDriver()),
            default => throw new InvalidArgumentException("Queue driver [{$name}] is not supported."),
        };
    }
}
