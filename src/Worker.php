<?php

declare(strict_types=1);

namespace WPQueue;

use Throwable;
use WPQueue\Contracts\JobInterface;
use WPQueue\Events\JobFailed;
use WPQueue\Events\JobProcessed;
use WPQueue\Events\JobProcessing;
use WPQueue\Events\JobRetrying;
use WPQueue\Storage\LogStorage;

class Worker
{
    protected int $startTime;

    protected int $jobsProcessed = 0;

    protected int $maxJobs = 0;

    protected int $maxTime = 0;

    protected int $memoryLimit = 128;

    protected ?LogStorage $logger = null;

    public function __construct(
        protected QueueManager $manager,
    ) {
        $this->startTime = time();
    }

    /**
     * Run the worker loop.
     */
    public function daemon(string $queue = 'default', int $sleep = 3): void
    {
        while (! $this->shouldStop()) {
            if (! $this->runNextJob($queue)) {
                sleep($sleep);
            }
        }
    }

    /**
     * Process the next job on the queue.
     */
    public function runNextJob(string $queue = 'default'): bool
    {
        $connection = $this->manager->connection();
        $job = $connection->pop($queue);

        if ($job === null) {
            return false;
        }

        return $this->process($job, $queue);
    }

    /**
     * Process a job.
     */
    public function process(JobInterface $job, string $queue): bool
    {
        try {
            $this->raiseBeforeJobEvent($job, $queue);

            $job->incrementAttempts();
            $job->handle();

            $this->raiseAfterJobEvent($job, $queue);
            $this->manager->connection()->delete($job->getId());

            $this->jobsProcessed++;
            $this->log('completed', $job);

            return true;
        } catch (Throwable $e) {
            return $this->handleJobException($job, $queue, $e);
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     */
    protected function handleJobException(JobInterface $job, string $queue, Throwable $e): bool
    {
        $this->log('failed', $job, $e->getMessage());

        if ($job->getAttempts() < $job->getMaxAttempts()) {
            $this->raiseRetryingEvent($job, $queue, $e);
            $this->manager->connection()->release($job, $this->calculateBackoff($job));

            return true;
        }

        try {
            $job->failed($e);
        } catch (Throwable) {
            // Ignore exceptions in failed handler
        }

        $this->raiseFailedJobEvent($job, $queue, $e);
        $this->manager->connection()->delete($job->getId());

        return false;
    }

    /**
     * Calculate exponential backoff delay.
     */
    protected function calculateBackoff(JobInterface $job): int
    {
        $attempts = $job->getAttempts();

        return min(2 ** $attempts, 3600); // Max 1 hour
    }

    /**
     * Check if we should stop the worker.
     */
    public function shouldStop(): bool
    {
        if ($this->maxJobs > 0 && $this->jobsProcessed >= $this->maxJobs) {
            return true;
        }

        if ($this->maxTime > 0 && (time() - $this->startTime) >= $this->maxTime) {
            return true;
        }

        if ($this->memoryExceeded()) {
            return true;
        }

        return false;
    }

    /**
     * Check if memory limit is exceeded.
     */
    public function memoryExceeded(): bool
    {
        $usage = memory_get_usage(true) / 1024 / 1024;

        return $usage >= $this->memoryLimit;
    }

    public function setMaxJobs(int $jobs): void
    {
        $this->maxJobs = $jobs;
    }

    public function setMaxTime(int $seconds): void
    {
        $this->maxTime = $seconds;
    }

    public function setMemoryLimit(int $megabytes): void
    {
        $this->memoryLimit = $megabytes;
    }

    public function getMaxJobs(): int
    {
        return $this->maxJobs;
    }

    public function getMaxTime(): int
    {
        return $this->maxTime;
    }

    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }

    public function setLogger(LogStorage $logger): void
    {
        $this->logger = $logger;
    }

    protected function log(string $status, JobInterface $job, ?string $message = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->log($status, $job, $message);
    }

    protected function raiseBeforeJobEvent(JobInterface $job, string $queue): void
    {
        if (function_exists('do_action')) {
            do_action('wp_queue_job_processing', new JobProcessing($job, $queue));
        }
    }

    protected function raiseAfterJobEvent(JobInterface $job, string $queue): void
    {
        if (function_exists('do_action')) {
            do_action('wp_queue_job_processed', new JobProcessed($job, $queue));
        }
    }

    protected function raiseFailedJobEvent(JobInterface $job, string $queue, Throwable $e): void
    {
        if (function_exists('do_action')) {
            do_action('wp_queue_job_failed', new JobFailed($job, $queue, $e));
        }
    }

    protected function raiseRetryingEvent(JobInterface $job, string $queue, Throwable $e): void
    {
        if (function_exists('do_action')) {
            do_action('wp_queue_job_retrying', new JobRetrying($job, $queue, $job->getAttempts(), $e));
        }
    }
}
