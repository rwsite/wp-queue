<?php

declare(strict_types=1);

namespace WPQueue;

use ReflectionClass;
use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;
use WPQueue\Attributes\Schedule;
use WPQueue\Attributes\Timeout;
use WPQueue\Contracts\JobInterface;

class Scheduler
{
    /**
     * @var array<string, ScheduledJob>
     */
    protected array $jobs = [];

    /**
     * @var array<string, array{interval: int, display: string}>
     */
    protected array $intervals = [];

    public function __construct()
    {
        $this->registerDefaultIntervals();
    }

    /**
     * Schedule a job class.
     *
     * @param  class-string<JobInterface>  $jobClass
     */
    public function job(string $jobClass): ScheduledJob
    {
        $scheduled = new ScheduledJob($jobClass, $this);
        $this->jobs[$jobClass] = $scheduled;

        // Apply attributes
        $this->applyAttributes($jobClass, $scheduled);

        return $scheduled;
    }

    /**
     * Register scheduled jobs with WP-Cron.
     */
    public function register(): void
    {
        // Register custom intervals
        add_filter('cron_schedules', [$this, 'registerIntervals']);

        // Register each job
        foreach ($this->jobs as $jobClass => $scheduled) {
            $this->registerJob($jobClass, $scheduled);
        }
    }

    /**
     * @param  array<string, array{interval: int, display: string}>  $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public function registerIntervals(array $schedules): array
    {
        return array_merge($schedules, $this->intervals);
    }

    /**
     * Add a custom interval.
     */
    public function addInterval(string $name, int $seconds, string $display): void
    {
        $this->intervals[$name] = [
            'interval' => $seconds,
            'display' => $display,
        ];
    }

    /**
     * Get all scheduled jobs.
     *
     * @return array<string, ScheduledJob>
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * @param  class-string<JobInterface>  $jobClass
     */
    protected function registerJob(string $jobClass, ScheduledJob $scheduled): void
    {
        $hook = $this->getHook($jobClass);
        $interval = $scheduled->getInterval();

        if (empty($interval)) {
            return;
        }

        // Check if should run based on condition
        if (! $scheduled->shouldRun()) {
            $this->unschedule($hook);

            return;
        }

        // Register action
        add_action($hook, function () use ($jobClass, $scheduled): void {
            $job = new $jobClass();

            // Apply scheduled settings
            if ($scheduled->getQueue()) {
                $job->onQueue($scheduled->getQueue());
            }
            if ($scheduled->getTimeout()) {
                $job->setTimeout($scheduled->getTimeout());
            }
            if ($scheduled->getRetries()) {
                $job->setMaxAttempts($scheduled->getRetries());
            }

            WPQueue::dispatch($job);
        });

        // Schedule if not already scheduled or interval changed
        $this->scheduleEvent($hook, $interval);
    }

    protected function scheduleEvent(string $hook, string $interval): void
    {
        $existing = wp_get_scheduled_event($hook);

        // If exists but different interval, reschedule
        if ($existing !== false && $existing->schedule !== $interval) {
            wp_clear_scheduled_hook($hook);
            $existing = false;
        }

        if ($existing === false) {
            wp_schedule_event(time(), $interval, $hook);
        }
    }

    protected function unschedule(string $hook): void
    {
        wp_clear_scheduled_hook($hook);
        wp_unschedule_hook($hook);
    }

    /**
     * @param  class-string<JobInterface>  $jobClass
     */
    protected function getHook(string $jobClass): string
    {
        $name = (new ReflectionClass($jobClass))->getShortName();

        return 'wp_queue_' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * @param  class-string<JobInterface>  $jobClass
     */
    protected function applyAttributes(string $jobClass, ScheduledJob $scheduled): void
    {
        $reflection = new ReflectionClass($jobClass);

        // Schedule attribute
        $scheduleAttrs = $reflection->getAttributes(Schedule::class);
        if (! empty($scheduleAttrs)) {
            $schedule = $scheduleAttrs[0]->newInstance();

            if ($schedule->setting) {
                $scheduled->interval(function_exists('get_option')
                    ? (string) get_option($schedule->setting, $schedule->interval)
                    : $schedule->interval);
            } else {
                $scheduled->interval($schedule->interval);
            }
        }

        // Queue attribute
        $queueAttrs = $reflection->getAttributes(Queue::class);
        if (! empty($queueAttrs)) {
            $queue = $queueAttrs[0]->newInstance();
            $scheduled->onQueue($queue->name);
        }

        // Timeout attribute
        $timeoutAttrs = $reflection->getAttributes(Timeout::class);
        if (! empty($timeoutAttrs)) {
            $timeout = $timeoutAttrs[0]->newInstance();
            $scheduled->timeout($timeout->seconds);
        }

        // Retries attribute
        $retriesAttrs = $reflection->getAttributes(Retries::class);
        if (! empty($retriesAttrs)) {
            $retries = $retriesAttrs[0]->newInstance();
            $scheduled->retries($retries->times);
        }
    }

    protected function registerDefaultIntervals(): void
    {
        $this->intervals = [
            'min' => ['interval' => 60, 'display' => __('Every Minute', 'wp-queue')],
            '5min' => ['interval' => 5 * 60, 'display' => __('Every 5 Minutes', 'wp-queue')],
            '10min' => ['interval' => 10 * 60, 'display' => __('Every 10 Minutes', 'wp-queue')],
            '15min' => ['interval' => 15 * 60, 'display' => __('Every 15 Minutes', 'wp-queue')],
            '30min' => ['interval' => 30 * 60, 'display' => __('Every 30 Minutes', 'wp-queue')],
            '2hourly' => ['interval' => 2 * HOUR_IN_SECONDS, 'display' => __('Every 2 Hours', 'wp-queue')],
            '3hourly' => ['interval' => 3 * HOUR_IN_SECONDS, 'display' => __('Every 3 Hours', 'wp-queue')],
            '6hourly' => ['interval' => 6 * HOUR_IN_SECONDS, 'display' => __('Every 6 Hours', 'wp-queue')],
            '8hourly' => ['interval' => 8 * HOUR_IN_SECONDS, 'display' => __('Every 8 Hours', 'wp-queue')],
            '12hourly' => ['interval' => 12 * HOUR_IN_SECONDS, 'display' => __('Every 12 Hours', 'wp-queue')],
        ];
    }
}
