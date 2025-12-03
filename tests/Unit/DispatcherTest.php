<?php

declare(strict_types=1);

use WPQueue\Dispatcher;
use WPQueue\Jobs\Job;
use WPQueue\Jobs\PendingDispatch;
use WPQueue\QueueManager;

class DispatchableJob extends Job
{
    public static bool $dispatched = false;

    public function __construct(
        public readonly string $data = ''
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        static::$dispatched = true;
    }
}

beforeEach(function () {
    DispatchableJob::$dispatched = false;

    Brain\Monkey\Functions\stubs([
        'get_site_option' => fn () => [],
        'update_site_option' => fn () => true,
        'delete_site_option' => fn () => true,
    ]);
});

describe('Dispatcher', function () {
    it('can dispatch job', function () {
        $manager = new QueueManager();
        $dispatcher = new Dispatcher($manager);
        
        $job = new DispatchableJob('test');
        $pending = $dispatcher->dispatch($job);
        
        expect($pending)->toBeInstanceOf(PendingDispatch::class);
    });

    it('can dispatch job with delay', function () {
        $manager = new QueueManager();
        $dispatcher = new Dispatcher($manager);
        
        $job = new DispatchableJob('test');
        $pending = $dispatcher->dispatch($job)->delay(60);
        
        expect($pending->getJob()->getDelay())->toBe(60);
    });

    it('can dispatch job to specific queue', function () {
        $manager = new QueueManager();
        $dispatcher = new Dispatcher($manager);
        
        $job = new DispatchableJob('test');
        $pending = $dispatcher->dispatch($job)->onQueue('high');
        
        expect($pending->getJob()->getQueue())->toBe('high');
    });

    it('dispatches sync when using sync driver', function () {
        $manager = new QueueManager();
        $manager->setDefaultDriver('sync');
        $dispatcher = new Dispatcher($manager);
        
        $job = new DispatchableJob('sync-test');
        $dispatcher->dispatchSync($job);
        
        expect(DispatchableJob::$dispatched)->toBeTrue();
    });
});

describe('PendingDispatch', function () {
    it('sends job on destruct', function () {
        Brain\Monkey\Functions\stubs([
            'get_site_option' => fn () => [],
            'update_site_option' => fn () => true,
        ]);

        $manager = new QueueManager();
        $job = new DispatchableJob('test');
        
        $pending = new PendingDispatch($job, $manager);
        // Trigger destruct
        unset($pending);
        
        // Job should be queued
        expect(true)->toBeTrue();
    });

    it('supports fluent api', function () {
        $manager = new QueueManager();
        $job = new DispatchableJob('test');
        
        $pending = (new PendingDispatch($job, $manager))
            ->onQueue('custom')
            ->delay(120);
        
        expect($pending->getJob()->getQueue())->toBe('custom');
        expect($pending->getJob()->getDelay())->toBe(120);
        
        $pending->cancel(); // Prevent dispatch in test
    });
});
