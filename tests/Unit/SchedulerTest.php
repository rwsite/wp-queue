<?php

declare(strict_types=1);

use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;
use WPQueue\Attributes\Schedule;
use WPQueue\Attributes\Timeout;
use WPQueue\Jobs\Job;
use WPQueue\ScheduledJob;
use WPQueue\Scheduler;

#[Schedule('hourly')]
#[Queue('imports')]
#[Timeout(120)]
#[Retries(5)]
class SchedulerTestJob extends Job
{
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void {}
}

#[Schedule('daily', setting: 'my_custom_interval')]
class SettingsBasedJob extends Job
{
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void {}
}

beforeEach(function () {
    Brain\Monkey\Functions\stubs([
        'add_filter' => fn () => true,
        'add_action' => fn () => true,
        'wp_get_scheduled_event' => fn () => false,
        'wp_schedule_event' => fn () => true,
        'wp_clear_scheduled_hook' => fn () => true,
        'wp_unschedule_hook' => fn () => true,
        'get_option' => fn ($key, $default = false) => $default,
        '__' => fn ($text) => $text,
    ]);
});

describe('Scheduler', function () {
    it('can schedule a job', function () {
        $scheduler = new Scheduler();
        $scheduled = $scheduler->job(SchedulerTestJob::class);
        
        expect($scheduled)->toBeInstanceOf(ScheduledJob::class);
        expect($scheduler->getJobs())->toHaveKey(SchedulerTestJob::class);
    });

    it('applies attributes from job class', function () {
        $scheduler = new Scheduler();
        $scheduled = $scheduler->job(SchedulerTestJob::class);
        
        expect($scheduled->getInterval())->toBe('hourly');
        expect($scheduled->getQueue())->toBe('imports');
        expect($scheduled->getTimeout())->toBe(120);
        expect($scheduled->getRetries())->toBe(5);
    });

    it('supports settings-based interval', function () {
        Brain\Monkey\Functions\stubs([
            'get_option' => fn ($key, $default = false) => $key === 'my_custom_interval' ? '6hourly' : $default,
        ]);
        
        $scheduler = new Scheduler();
        $scheduled = $scheduler->job(SettingsBasedJob::class);
        
        expect($scheduled->getInterval())->toBe('6hourly');
    });

    it('has default intervals', function () {
        $scheduler = new Scheduler();
        $intervals = $scheduler->registerIntervals([]);
        
        expect($intervals)->toHaveKey('min');
        expect($intervals)->toHaveKey('5min');
        expect($intervals)->toHaveKey('2hourly');
        expect($intervals['min']['interval'])->toBe(60);
    });

    it('can add custom intervals', function () {
        $scheduler = new Scheduler();
        $scheduler->addInterval('7min', 420, 'Every 7 Minutes');
        
        $intervals = $scheduler->registerIntervals([]);
        
        expect($intervals)->toHaveKey('7min');
        expect($intervals['7min']['interval'])->toBe(420);
    });
});

describe('ScheduledJob', function () {
    it('supports fluent interval methods', function () {
        $scheduler = new Scheduler();
        
        $job = new ScheduledJob('TestJob', $scheduler);
        
        $job->everyMinute();
        expect($job->getInterval())->toBe('min');
        
        $job->everyFiveMinutes();
        expect($job->getInterval())->toBe('5min');
        
        $job->hourly();
        expect($job->getInterval())->toBe('hourly');
        
        $job->daily();
        expect($job->getInterval())->toBe('daily');
        
        $job->weekly();
        expect($job->getInterval())->toBe('weekly');
    });

    it('supports custom minute intervals', function () {
        $scheduler = new Scheduler();
        $job = new ScheduledJob('TestJob', $scheduler);
        
        $job->everyMinutes(7);
        
        expect($job->getInterval())->toBe('7min');
        
        $intervals = $scheduler->registerIntervals([]);
        expect($intervals)->toHaveKey('7min');
        expect($intervals['7min']['interval'])->toBe(420);
    });

    it('supports conditional execution', function () {
        $scheduler = new Scheduler();
        $job = new ScheduledJob('TestJob', $scheduler);
        
        $job->when(fn () => true);
        expect($job->shouldRun())->toBeTrue();
        
        $job->when(fn () => false);
        expect($job->shouldRun())->toBeFalse();
    });

    it('supports skip condition', function () {
        $scheduler = new Scheduler();
        $job = new ScheduledJob('TestJob', $scheduler);
        
        $job->skip(fn () => true);
        expect($job->shouldRun())->toBeFalse();
        
        $job->skip(fn () => false);
        expect($job->shouldRun())->toBeTrue();
    });
});
