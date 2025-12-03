<?php

declare(strict_types=1);

use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;
use WPQueue\Attributes\Schedule;
use WPQueue\Attributes\Timeout;
use WPQueue\Attributes\UniqueJob;
use WPQueue\Jobs\Job;

#[Schedule('hourly')]
#[Queue('imports')]
#[Timeout(120)]
#[Retries(5)]
#[UniqueJob]
class AttributedJob extends Job
{
    public function handle(): void {}
}

#[Schedule('5min', setting: 'my_setting')]
class SettingBasedJob extends Job
{
    public function handle(): void {}
}

describe('Schedule Attribute', function (): void {
    it('can be read from class', function (): void {
        $reflection = new ReflectionClass(AttributedJob::class);
        $attributes = $reflection->getAttributes(Schedule::class);

        expect($attributes)->toHaveCount(1);

        $schedule = $attributes[0]->newInstance();
        expect($schedule->interval)->toBe('hourly');
        expect($schedule->setting)->toBeNull();
    });

    it('supports setting-based interval', function (): void {
        $reflection = new ReflectionClass(SettingBasedJob::class);
        $attributes = $reflection->getAttributes(Schedule::class);

        $schedule = $attributes[0]->newInstance();
        expect($schedule->interval)->toBe('5min');
        expect($schedule->setting)->toBe('my_setting');
    });
});

describe('Queue Attribute', function (): void {
    it('can be read from class', function (): void {
        $reflection = new ReflectionClass(AttributedJob::class);
        $attributes = $reflection->getAttributes(Queue::class);

        expect($attributes)->toHaveCount(1);

        $queue = $attributes[0]->newInstance();
        expect($queue->name)->toBe('imports');
    });
});

describe('Timeout Attribute', function (): void {
    it('can be read from class', function (): void {
        $reflection = new ReflectionClass(AttributedJob::class);
        $attributes = $reflection->getAttributes(Timeout::class);

        expect($attributes)->toHaveCount(1);

        $timeout = $attributes[0]->newInstance();
        expect($timeout->seconds)->toBe(120);
    });
});

describe('Retries Attribute', function (): void {
    it('can be read from class', function (): void {
        $reflection = new ReflectionClass(AttributedJob::class);
        $attributes = $reflection->getAttributes(Retries::class);

        expect($attributes)->toHaveCount(1);

        $retries = $attributes[0]->newInstance();
        expect($retries->times)->toBe(5);
    });
});

describe('UniqueJob Attribute', function (): void {
    it('can be read from class', function (): void {
        $reflection = new ReflectionClass(AttributedJob::class);
        $attributes = $reflection->getAttributes(UniqueJob::class);

        expect($attributes)->toHaveCount(1);

        $unique = $attributes[0]->newInstance();
        expect($unique->key)->toBeNull();
    });
});
