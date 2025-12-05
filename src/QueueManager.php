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
     * @var array<string, QueueInterface>
     */
    protected array $connections = [];

    /**
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    protected ?string $defaultDriver = null;

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
     * 2. WP_QUEUE_DRIVER constant
     * 3. 'database' as fallback
     */
    public function getDefaultDriver(): string
    {
        if ($this->defaultDriver !== null) {
            return $this->defaultDriver;
        }

        // Check WP_QUEUE_DRIVER constant
        if (defined('WP_QUEUE_DRIVER')) {
            $driver = WP_QUEUE_DRIVER;

            if ($driver === 'auto') {
                return $this->detectBestDriver();
            }

            return $driver;
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
        if (RedisQueue::isAvailable()) {
            return 'redis';
        }

        if (MemcachedQueue::isAvailable()) {
            return 'memcached';
        }

        return 'database';
    }

    /**
     * Get all available drivers.
     *
     * @return array<string, array{available: bool, info: string}>
     */
    public function getAvailableDrivers(): array
    {
        $drivers = [
            'database' => [
                'available' => true,
                'info' => 'Uses wp_options table',
            ],
            'sync' => [
                'available' => true,
                'info' => 'Synchronous execution (no queue)',
            ],
            'redis' => [
                'available' => RedisQueue::isAvailable(),
                'info' => extension_loaded('redis')
                    ? 'Redis server at '.(defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1').':'.(defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379)
                    : 'phpredis extension not installed',
            ],
            'memcached' => [
                'available' => MemcachedQueue::isAvailable(),
                'info' => extension_loaded('memcached')
                    ? 'Memcached server at '.(defined('WP_MEMCACHED_HOST') ? WP_MEMCACHED_HOST : '127.0.0.1').':'.(defined('WP_MEMCACHED_PORT') ? WP_MEMCACHED_PORT : 11211)
                    : 'memcached extension not installed',
            ],
        ];

        // Add custom drivers
        foreach (array_keys($this->customCreators) as $name) {
            $drivers[$name] = [
                'available' => true,
                'info' => 'Custom driver',
            ];
        }

        return $drivers;
    }

    /**
     * Check if a driver is available.
     */
    public function isDriverAvailable(string $driver): bool
    {
        return match ($driver) {
            'database', 'sync' => true,
            'redis' => RedisQueue::isAvailable(),
            'memcached' => MemcachedQueue::isAvailable(),
            default => isset($this->customCreators[$driver]),
        };
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
