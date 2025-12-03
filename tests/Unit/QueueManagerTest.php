<?php

declare(strict_types=1);

use WPQueue\Contracts\JobInterface;
use WPQueue\Jobs\Job;
use WPQueue\Queue\DatabaseQueue;
use WPQueue\QueueManager;

class SimpleJob extends Job
{
    public function __construct(
        public readonly string $payload = ''
    ) {
        parent::__construct();
    }

    public function handle(): void {}
}

beforeEach(function () {
    // Mock WordPress functions
    Brain\Monkey\Functions\stubs([
        'get_site_option' => function ($key, $default = false) {
            return $default;
        },
        'update_site_option' => function ($key, $value) {
            return true;
        },
        'delete_site_option' => function ($key) {
            return true;
        },
    ]);
});

describe('QueueManager', function () {
    it('can resolve queue by name', function () {
        $manager = new QueueManager();
        $queue = $manager->connection('database');
        
        expect($queue)->toBeInstanceOf(DatabaseQueue::class);
    });

    it('can add custom queue driver', function () {
        $manager = new QueueManager();
        $customQueue = Mockery::mock(WPQueue\Contracts\QueueInterface::class);
        
        $manager->extend('custom', fn () => $customQueue);
        $resolved = $manager->connection('custom');
        
        expect($resolved)->toBe($customQueue);
    });

    it('returns default connection', function () {
        $manager = new QueueManager();
        $default = $manager->getDefaultDriver();
        
        expect($default)->toBe('database');
    });
});

describe('DatabaseQueue', function () {
    it('can push job to queue', function () {
        Brain\Monkey\Functions\stubs([
            'get_site_option' => fn () => [],
            'update_site_option' => fn () => true,
        ]);
        
        $queue = new DatabaseQueue();
        $job = new SimpleJob('test');
        
        $id = $queue->push($job);
        
        expect($id)->toBe($job->getId());
    });

    it('can check if queue is empty', function () {
        Brain\Monkey\Functions\stubs([
            'get_site_option' => fn () => [],
        ]);
        
        $queue = new DatabaseQueue();
        
        expect($queue->isEmpty('default'))->toBeTrue();
    });

    it('can get queue size', function () {
        $job1 = new SimpleJob();
        $job2 = new SimpleJob();
        
        Brain\Monkey\Functions\stubs([
            'get_site_option' => fn () => [
                $job1->getId() => ['payload' => serialize($job1), 'available_at' => time(), 'reserved_at' => null, 'attempts' => 0],
                $job2->getId() => ['payload' => serialize($job2), 'available_at' => time(), 'reserved_at' => null, 'attempts' => 0],
            ],
        ]);
        
        $queue = new DatabaseQueue();
        
        expect($queue->size('default'))->toBe(2);
    });
});
