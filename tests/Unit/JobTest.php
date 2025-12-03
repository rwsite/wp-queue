<?php

declare(strict_types=1);

use WPQueue\Contracts\JobInterface;
use WPQueue\Contracts\ShouldQueue;
use WPQueue\Jobs\Job;

// Test fixtures
class TestJob extends Job
{
    public bool $handled = false;

    public function __construct(
        public readonly string $data = 'test',
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->handled = true;
    }
}

class FailingJob extends Job
{
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        throw new RuntimeException('Job failed');
    }

    public function failed(Throwable $e): void
    {
        // Custom failure handler
    }
}

describe('Job', function (): void {
    it('implements JobInterface', function (): void {
        $job = new TestJob();
        expect($job)->toBeInstanceOf(JobInterface::class);
    });

    it('implements ShouldQueue', function (): void {
        $job = new TestJob();
        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('can be executed', function (): void {
        $job = new TestJob();
        $job->handle();
        expect($job->handled)->toBeTrue();
    });

    it('has unique id', function (): void {
        $job1 = new TestJob();
        $job2 = new TestJob();
        expect($job1->getId())->not->toBe($job2->getId());
    });

    it('tracks attempts', function (): void {
        $job = new TestJob();
        expect($job->getAttempts())->toBe(0);

        $job->incrementAttempts();
        expect($job->getAttempts())->toBe(1);
    });

    it('can set max attempts', function (): void {
        $job = new TestJob();
        $job->setMaxAttempts(5);
        expect($job->getMaxAttempts())->toBe(5);
    });

    it('can set timeout', function (): void {
        $job = new TestJob();
        $job->setTimeout(120);
        expect($job->getTimeout())->toBe(120);
    });

    it('can be serialized', function (): void {
        // Create simple job without readonly properties for serialization test
        $job = new FailingJob();
        $originalId = $job->getId();
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(FailingJob::class);
        expect($unserialized->getId())->toBe($originalId);
    });

    it('can set queue name', function (): void {
        $job = new TestJob();
        $job->onQueue('high-priority');
        expect($job->getQueue())->toBe('high-priority');
    });

    it('can set delay', function (): void {
        $job = new TestJob();
        $job->delay(60);
        expect($job->getDelay())->toBe(60);
    });

    it('has default queue name', function (): void {
        $job = new TestJob();
        expect($job->getQueue())->toBe('default');
    });

    it('has default timeout', function (): void {
        $job = new TestJob();
        expect($job->getTimeout())->toBe(60);
    });

    it('has default max attempts', function (): void {
        $job = new TestJob();
        expect($job->getMaxAttempts())->toBe(3);
    });
});

describe('Job Metadata', function (): void {
    it('stores created timestamp', function (): void {
        $before = time();
        $job = new TestJob();
        $after = time();

        expect($job->getCreatedAt())->toBeGreaterThanOrEqual($before);
        expect($job->getCreatedAt())->toBeLessThanOrEqual($after);
    });

    it('can convert to array', function (): void {
        $job = new TestJob('test-data');
        $array = $job->toArray();

        expect($array)->toHaveKey('id');
        expect($array)->toHaveKey('class');
        expect($array)->toHaveKey('queue');
        expect($array)->toHaveKey('attempts');
        expect($array)->toHaveKey('max_attempts');
        expect($array)->toHaveKey('timeout');
        expect($array)->toHaveKey('created_at');
        expect($array['class'])->toBe(TestJob::class);
    });
});
