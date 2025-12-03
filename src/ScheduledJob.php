<?php

declare(strict_types=1);

namespace WPQueue;

class ScheduledJob
{
    protected string $interval = '';

    protected ?string $queue = null;

    protected ?int $timeout = null;

    protected ?int $retries = null;

    /** @var callable|null */
    protected $condition = null;

    /**
     * @param  class-string  $jobClass
     */
    public function __construct(
        protected string $jobClass,
        protected Scheduler $scheduler,
    ) {}

    /**
     * Set the schedule interval.
     */
    public function interval(string $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * Run every N minutes.
     */
    public function everyMinutes(int $minutes): static
    {
        $name = $minutes . 'min';
        $this->scheduler->addInterval($name, $minutes * 60, sprintf(__('Every %d Minutes', 'wp-queue'), $minutes));

        return $this->interval($name);
    }

    /**
     * Run every minute.
     */
    public function everyMinute(): static
    {
        return $this->interval('min');
    }

    /**
     * Run every 5 minutes.
     */
    public function everyFiveMinutes(): static
    {
        return $this->interval('5min');
    }

    /**
     * Run every 15 minutes.
     */
    public function everyFifteenMinutes(): static
    {
        return $this->interval('15min');
    }

    /**
     * Run every 30 minutes.
     */
    public function everyThirtyMinutes(): static
    {
        return $this->interval('30min');
    }

    /**
     * Run hourly.
     */
    public function hourly(): static
    {
        return $this->interval('hourly');
    }

    /**
     * Run every 2 hours.
     */
    public function everyTwoHours(): static
    {
        return $this->interval('2hourly');
    }

    /**
     * Run daily.
     */
    public function daily(): static
    {
        return $this->interval('daily');
    }

    /**
     * Run twice daily.
     */
    public function twiceDaily(): static
    {
        return $this->interval('twicedaily');
    }

    /**
     * Run weekly.
     */
    public function weekly(): static
    {
        return $this->interval('weekly');
    }

    /**
     * Set queue name.
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set timeout.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set retries.
     */
    public function retries(int $times): static
    {
        $this->retries = $times;

        return $this;
    }

    /**
     * Set condition for running.
     */
    public function when(callable $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Skip when condition is true.
     */
    public function skip(callable $condition): static
    {
        $this->condition = fn () => ! $condition();

        return $this;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getRetries(): ?int
    {
        return $this->retries;
    }

    public function shouldRun(): bool
    {
        if ($this->condition === null) {
            return true;
        }

        return (bool) ($this->condition)();
    }
}
