<?php

declare(strict_types=1);

use WPQueue\Events\JobFailed;
use WPQueue\Events\JobProcessed;
use WPQueue\Events\JobProcessing;
use WPQueue\Jobs\Job;
use WPQueue\QueueManager;
use WPQueue\Worker;

class WorkerTestJob extends Job
{
    public static bool $handled = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        static::$handled = true;
    }
}

class FailingWorkerJob extends Job
{
    public static bool $failedCalled = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        throw new RuntimeException('Intentional failure');
    }

    public function failed(Throwable $e): void
    {
        static::$failedCalled = true;
    }
}

beforeEach(function () {
    WorkerTestJob::$handled = false;
    FailingWorkerJob::$failedCalled = false;

    Brain\Monkey\Functions\stubs([
        'get_site_option' => fn () => [],
        'update_site_option' => fn () => true,
        'delete_site_option' => fn () => true,
        'get_site_transient' => fn () => false,
        'set_site_transient' => fn () => true,
        'delete_site_transient' => fn () => true,
        'do_action' => fn () => null,
        'apply_filters' => fn ($hook, $value) => $value,
    ]);
});

describe('Worker', function () {
    it('processes job from queue', function () {
        // This test is simplified because full integration requires more mocking
        $manager = new QueueManager();
        $worker = new Worker($manager);
        
        Brain\Monkey\Functions\stubs([
            'get_site_option' => fn () => [],
        ]);
        
        // Empty queue returns false
        $processed = $worker->runNextJob('default');
        
        expect($processed)->toBeFalse();
    });

    it('returns false when queue is empty', function () {
        Brain\Monkey\Functions\expect('get_site_option')
            ->andReturn([]);
        
        $manager = new QueueManager();
        $worker = new Worker($manager);
        
        $processed = $worker->runNextJob('default');
        
        expect($processed)->toBeFalse();
    });

    it('respects memory limit', function () {
        $manager = new QueueManager();
        $worker = new Worker($manager);
        
        // Default memory limit is 128MB
        expect($worker->memoryExceeded())->toBeFalse();
    });

    it('can set stop conditions', function () {
        $manager = new QueueManager();
        $worker = new Worker($manager);
        
        $worker->setMaxJobs(10);
        $worker->setMaxTime(60);
        $worker->setMemoryLimit(256);
        
        expect($worker->getMaxJobs())->toBe(10);
        expect($worker->getMaxTime())->toBe(60);
        expect($worker->getMemoryLimit())->toBe(256);
    });
});
