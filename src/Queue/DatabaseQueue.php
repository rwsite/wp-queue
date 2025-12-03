<?php

declare(strict_types=1);

namespace WPQueue\Queue;

use WPQueue\Contracts\JobInterface;
use WPQueue\Contracts\QueueInterface;

class DatabaseQueue implements QueueInterface
{
    protected const PREFIX = 'wp_queue_';

    public function push(JobInterface $job): string
    {
        $queue = $job->getQueue();
        $availableAt = time() + $job->getDelay();

        $this->store($queue, $job, $availableAt);

        return $job->getId();
    }

    public function later(int $delay, JobInterface $job): string
    {
        $job->delay($delay);

        return $this->push($job);
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        $jobs = $this->getQueue($queue);

        if (empty($jobs)) {
            return null;
        }

        $now = time();

        foreach ($jobs as $id => $data) {
            if ($data['available_at'] <= $now && $data['reserved_at'] === null) {
                // Reserve the job
                $jobs[$id]['reserved_at'] = $now;
                $this->saveQueue($queue, $jobs);

                $job = unserialize($data['payload']);
                if ($job instanceof JobInterface) {
                    return $job;
                }
            }
        }

        return null;
    }

    public function delete(string $jobId): bool
    {
        foreach ($this->getAllQueues() as $queue) {
            $jobs = $this->getQueue($queue);

            if (isset($jobs[$jobId])) {
                unset($jobs[$jobId]);
                $this->saveQueue($queue, $jobs);

                return true;
            }
        }

        return false;
    }

    public function release(JobInterface $job, int $delay = 0): void
    {
        $queue = $job->getQueue();
        $jobs = $this->getQueue($queue);

        if (isset($jobs[$job->getId()])) {
            $jobs[$job->getId()]['reserved_at'] = null;
            $jobs[$job->getId()]['available_at'] = time() + $delay;
            $this->saveQueue($queue, $jobs);
        }
    }

    public function size(string $queue = 'default'): int
    {
        return count($this->getQueue($queue));
    }

    public function clear(string $queue = 'default'): int
    {
        $count = $this->size($queue);
        delete_site_option($this->queueKey($queue));

        return $count;
    }

    public function isEmpty(string $queue = 'default'): bool
    {
        return $this->size($queue) === 0;
    }

    /**
     * @return array<string, array{payload: string, available_at: int, reserved_at: int|null, attempts: int}>
     */
    protected function getQueue(string $queue): array
    {
        return get_site_option($this->queueKey($queue), []);
    }

    /**
     * @param  array<string, array{payload: string, available_at: int, reserved_at: int|null, attempts: int}>  $jobs
     */
    protected function saveQueue(string $queue, array $jobs): void
    {
        if (empty($jobs)) {
            delete_site_option($this->queueKey($queue));
        } else {
            update_site_option($this->queueKey($queue), $jobs);
        }
    }

    protected function store(string $queue, JobInterface $job, int $availableAt): void
    {
        $jobs = $this->getQueue($queue);

        $jobs[$job->getId()] = [
            'payload' => serialize($job),
            'available_at' => $availableAt,
            'reserved_at' => null,
            'attempts' => $job->getAttempts(),
        ];

        update_site_option($this->queueKey($queue), $jobs);
    }

    protected function queueKey(string $queue): string
    {
        return self::PREFIX.'jobs_'.$queue;
    }

    /**
     * @return string[]
     */
    protected function getAllQueues(): array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return ['default'];
        }

        $prefix = self::PREFIX.'jobs_';
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix.'%',
            ),
        );

        return array_map(fn ($key) => str_replace($prefix, '', $key), $results);
    }
}
