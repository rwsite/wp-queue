<?php

declare(strict_types=1);

use WPQueue\Queue\Redis\PhpRedisClient;
use WPQueue\Queue\Redis\PredisClient;
use WPQueue\Queue\Redis\RedisClientFactory;
use WPQueue\Queue\Redis\RedisClientInterface;

describe('RedisClientFactory', function (): void {
    beforeEach(function (): void {
        // Mock WordPress functions
        Brain\Monkey\Functions\stubs([
            '__' => fn ($text) => $text,
        ]);
    });

    it('reports availability correctly', function (): void {
        $available = RedisClientFactory::isAvailable();

        // Should be true if either phpredis or predis is available
        expect($available)->toBeBool();
    });

    it('reports phpredis availability', function (): void {
        $available = RedisClientFactory::isPhpRedisAvailable();

        // Should match extension_loaded('redis')
        expect($available)->toBe(extension_loaded('redis'));
    });

    it('reports predis availability', function (): void {
        $available = RedisClientFactory::isPredisAvailable();

        // Should be true if Predis class exists or redis-cache plugin is installed
        expect($available)->toBeBool();
    });

    it('returns available clients list', function (): void {
        $clients = RedisClientFactory::getAvailableClients();

        expect($clients)->toBeArray();

        if (extension_loaded('redis')) {
            expect($clients)->toContain('phpredis');
        }

        if (class_exists('\Predis\Client') || (defined('WP_REDIS_PLUGIN_PATH') && file_exists(WP_REDIS_PLUGIN_PATH.'/dependencies/predis/predis/autoload.php'))) {
            expect($clients)->toContain('predis');
        }
    });

    it('creates client when available', function (): void {
        if (! RedisClientFactory::isAvailable()) {
            $this->markTestSkipped('No Redis client available');
        }

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'test_',
            'scheme' => 'tcp',
            'path' => null,
            'timeout' => 1.0,
            'read_timeout' => 1.0,
        ];

        $client = RedisClientFactory::create($config);

        expect($client)->toBeInstanceOf(RedisClientInterface::class);
    });

    it('prefers phpredis over predis', function (): void {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension not available');
        }

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'test_',
            'scheme' => 'tcp',
            'path' => null,
            'timeout' => 1.0,
            'read_timeout' => 1.0,
        ];

        $client = RedisClientFactory::create($config);

        expect($client)->toBeInstanceOf(PhpRedisClient::class);
        expect($client->getClientType())->toBe('phpredis');
    });

    it('throws exception when no client available', function (): void {
        if (RedisClientFactory::isAvailable()) {
            $this->markTestSkipped('Redis client is available, cannot test exception');
        }

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'test_',
            'scheme' => 'tcp',
            'path' => null,
            'timeout' => 1.0,
            'read_timeout' => 1.0,
        ];

        expect(fn () => RedisClientFactory::create($config))
            ->toThrow(\RuntimeException::class);
    });
});

describe('RedisClientInterface implementations', function (): void {
    beforeEach(function (): void {
        Brain\Monkey\Functions\stubs([
            '__' => fn ($text) => $text,
        ]);
    });

    it('PhpRedisClient implements interface correctly', function (): void {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension not available');
        }

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'test_',
            'scheme' => 'tcp',
            'path' => null,
            'timeout' => 1.0,
            'read_timeout' => 1.0,
        ];

        $client = new PhpRedisClient($config);

        expect($client)->toBeInstanceOf(RedisClientInterface::class);
        expect($client->getClientType())->toBe('phpredis');
        expect($client->isConnected())->toBeFalse();
    });

    it('PredisClient implements interface correctly', function (): void {
        if (! RedisClientFactory::isPredisAvailable()) {
            $this->markTestSkipped('Predis not available');
        }

        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'prefix' => 'test_',
            'scheme' => 'tcp',
            'path' => null,
            'timeout' => 1.0,
            'read_timeout' => 1.0,
        ];

        $client = new PredisClient($config);

        expect($client)->toBeInstanceOf(RedisClientInterface::class);
        expect($client->getClientType())->toBe('predis');
        expect($client->isConnected())->toBeFalse();
    });
});
