<?php

declare(strict_types=1);

use WPQueue\Queue\Redis\RedisClientFactory;
use WPQueue\QueueManager;

describe('QueueManager Redis Status', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\stubs([
            '__' => fn ($text, $domain = 'default') => $text,
            'get_site_option' => fn () => false,
            'update_site_option' => fn () => true,
        ]);
    });

    it('returns correct status constants', function (): void {
        expect(QueueManager::STATUS_READY)->toBe('ready');
        expect(QueueManager::STATUS_NO_EXTENSION)->toBe('no_extension');
        expect(QueueManager::STATUS_NO_SERVER)->toBe('no_server');
        expect(QueueManager::STATUS_UNAVAILABLE)->toBe('unavailable');
    });

    it('getDriverStatus returns array with required keys', function (): void {
        $manager = new QueueManager();
        $status = $manager->getDriverStatus('redis');

        expect($status)->toBeArray();
        expect($status)->toHaveKeys(['status', 'extension', 'server', 'message']);
    });

    it('database driver is always ready', function (): void {
        $manager = new QueueManager();
        $status = $manager->getDriverStatus('database');

        expect($status['status'])->toBe(QueueManager::STATUS_READY);
        expect($status['extension'])->toBeTrue();
        expect($status['server'])->toBeTrue();
    });

    it('sync driver is always ready', function (): void {
        $manager = new QueueManager();
        $status = $manager->getDriverStatus('sync');

        expect($status['status'])->toBe(QueueManager::STATUS_READY);
        expect($status['extension'])->toBeTrue();
        expect($status['server'])->toBeTrue();
    });

    it('unknown driver returns unavailable status', function (): void {
        $manager = new QueueManager();
        $status = $manager->getDriverStatus('unknown_driver');

        expect($status['status'])->toBe(QueueManager::STATUS_UNAVAILABLE);
        expect($status['extension'])->toBeFalse();
        expect($status['server'])->toBeFalse();
    });

    it('redis status reflects client availability', function (): void {
        $manager = new QueueManager();
        $status = $manager->getDriverStatus('redis');

        if (RedisClientFactory::isAvailable()) {
            // If client available, status should be ready or no_server
            expect($status['status'])->toBeIn([
                QueueManager::STATUS_READY,
                QueueManager::STATUS_NO_SERVER,
            ]);
            expect($status['extension'])->toBeTrue();
        } else {
            // If no client, should be no_extension
            expect($status['status'])->toBe(QueueManager::STATUS_NO_EXTENSION);
            expect($status['extension'])->toBeFalse();
        }
    });

    it('isDriverReady returns boolean', function (): void {
        $manager = new QueueManager();

        expect($manager->isDriverReady('database'))->toBeTrue();
        expect($manager->isDriverReady('sync'))->toBeTrue();
        expect($manager->isDriverReady('unknown'))->toBeFalse();
    });

    it('getAvailableDrivers returns all drivers', function (): void {
        $manager = new QueueManager();
        $drivers = $manager->getAvailableDrivers();

        expect($drivers)->toHaveKeys(['database', 'sync', 'redis', 'memcached']);

        foreach ($drivers as $name => $info) {
            expect($info)->toHaveKeys(['status', 'extension', 'server', 'message', 'available']);
        }
    });

    it('caches driver status', function (): void {
        $manager = new QueueManager();

        // First call
        $status1 = $manager->getDriverStatus('database');

        // Second call should return cached result
        $status2 = $manager->getDriverStatus('database');

        expect($status1)->toBe($status2);
    });
});

describe('QueueManager Redis with Plugin', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\stubs([
            '__' => fn ($text, $domain = 'default') => $text,
            'get_site_option' => fn () => false,
            'update_site_option' => fn () => true,
        ]);
    });

    it('detects redis-cache plugin when available', function (): void {
        // This test verifies the logic, actual plugin detection
        // depends on WordPress environment
        $manager = new QueueManager();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('isRedisObjectCachePluginAvailable');
        $method->setAccessible(true);

        $result = $method->invoke($manager);

        // Result depends on whether redis_object_cache() function exists
        expect($result)->toBeBool();
    });

    it('detects predis availability', function (): void {
        $manager = new QueueManager();

        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('isPredisAvailable');
        $method->setAccessible(true);

        $result = $method->invoke($manager);

        // Should match factory's check
        expect($result)->toBe(RedisClientFactory::isPredisAvailable());
    });
});
