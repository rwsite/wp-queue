<?php

declare(strict_types=1);

namespace WPQueue\Storage;

use WPQueue\Contracts\JobInterface;

class LogStorage
{
    protected const OPTION_KEY = 'wp_queue_logs';

    protected const MAX_LOGS = 1000;

    /**
     * Log a job event.
     */
    public function log(string $status, JobInterface $job, ?string $message = null): void
    {
        $logs = $this->all();

        $logs[] = [
            'id' => bin2hex(random_bytes(8)),
            'job_id' => $job->getId(),
            'job_class' => $job::class,
            'queue' => $job->getQueue(),
            'status' => $status,
            'message' => $message,
            'attempts' => $job->getAttempts(),
            'timestamp' => time(),
        ];

        // Keep only last N logs
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, -self::MAX_LOGS);
        }

        update_site_option(self::OPTION_KEY, $logs);
    }

    /**
     * Get all logs.
     *
     * @return array<int, array{id: string, job_id: string, job_class: string, queue: string, status: string, message: ?string, attempts: int, timestamp: int}>
     */
    public function all(): array
    {
        return get_site_option(self::OPTION_KEY, []);
    }

    /**
     * Get recent logs.
     *
     * @return array<int, array{id: string, job_id: string, job_class: string, queue: string, status: string, message: ?string, attempts: int, timestamp: int}>
     */
    public function recent(int $limit = 100): array
    {
        $logs = $this->all();

        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Get logs for a specific job class.
     *
     * @param  class-string  $jobClass
     * @return array<int, array{id: string, job_id: string, job_class: string, queue: string, status: string, message: ?string, attempts: int, timestamp: int}>
     */
    public function forJob(string $jobClass): array
    {
        return array_filter($this->all(), fn ($log) => $log['job_class'] === $jobClass);
    }

    /**
     * Get failed logs.
     *
     * @return array<int, array{id: string, job_id: string, job_class: string, queue: string, status: string, message: ?string, attempts: int, timestamp: int}>
     */
    public function failed(): array
    {
        return array_filter($this->all(), fn ($log) => $log['status'] === 'failed');
    }

    /**
     * Get completed logs.
     *
     * @return array<int, array{id: string, job_id: string, job_class: string, queue: string, status: string, message: ?string, attempts: int, timestamp: int}>
     */
    public function completed(): array
    {
        return array_filter($this->all(), fn ($log) => $log['status'] === 'completed');
    }

    /**
     * Clear old logs.
     */
    public function clearOld(int $daysOld = 7): int
    {
        $cutoff = time() - ($daysOld * DAY_IN_SECONDS);
        $logs = $this->all();
        $original = count($logs);

        $logs = array_filter($logs, fn ($log) => $log['timestamp'] >= $cutoff);

        update_site_option(self::OPTION_KEY, array_values($logs));

        return $original - count($logs);
    }

    /**
     * Clear all logs.
     */
    public function clear(): void
    {
        delete_site_option(self::OPTION_KEY);
    }

    /**
     * Get metrics.
     *
     * @return array{total: int, completed: int, failed: int, by_queue: array<string, int>, by_job: array<string, int>}
     */
    public function metrics(): array
    {
        $logs = $this->all();

        $metrics = [
            'total' => count($logs),
            'completed' => 0,
            'failed' => 0,
            'by_queue' => [],
            'by_job' => [],
        ];

        foreach ($logs as $log) {
            if ($log['status'] === 'completed') {
                $metrics['completed']++;
            } elseif ($log['status'] === 'failed') {
                $metrics['failed']++;
            }

            $metrics['by_queue'][$log['queue']] = ($metrics['by_queue'][$log['queue']] ?? 0) + 1;
            $metrics['by_job'][$log['job_class']] = ($metrics['by_job'][$log['job_class']] ?? 0) + 1;
        }

        return $metrics;
    }
}
