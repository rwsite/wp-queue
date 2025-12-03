<?php

declare(strict_types=1);

namespace WPQueue\Jobs;

use WPQueue\Contracts\JobInterface;
use WPQueue\QueueManager;

class PendingDispatch
{
    protected bool $shouldDispatch = true;

    public function __construct(
        protected JobInterface $job,
        protected QueueManager $manager,
    ) {}

    public function __destruct()
    {
        if ($this->shouldDispatch) {
            $this->send();
        }
    }

    /**
     * Set the queue name.
     */
    public function onQueue(string $queue): static
    {
        $this->job->onQueue($queue);

        return $this;
    }

    /**
     * Set the delay in seconds.
     */
    public function delay(int $seconds): static
    {
        $this->job->delay($seconds);

        return $this;
    }

    /**
     * Cancel the dispatch.
     */
    public function cancel(): void
    {
        $this->shouldDispatch = false;
    }

    /**
     * Get the underlying job.
     */
    public function getJob(): JobInterface
    {
        return $this->job;
    }

    /**
     * Actually send the job to the queue.
     */
    protected function send(): void
    {
        $queue = $this->manager->connection();
        $queue->push($this->job);
    }
}
