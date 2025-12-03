<?php

declare(strict_types=1);

namespace WPQueue;

use WPQueue\Admin\AdminPage;
use WPQueue\Admin\RestApi;
use WPQueue\Contracts\JobInterface;
use WPQueue\Jobs\PendingDispatch;
use WPQueue\Storage\LogStorage;

/**
 * Main facade for WP Queue.
 *
 * @method static PendingDispatch dispatch(JobInterface $job)
 * @method static void dispatchSync(JobInterface $job)
 * @method static void dispatchNow(JobInterface $job)
 * @method static PendingChain chain(array $jobs)
 * @method static PendingBatch batch(array $jobs)
 */
final class WPQueue
{
    private static ?self $instance = null;

    private QueueManager $manager;

    private Dispatcher $dispatcher;

    private Scheduler $scheduler;

    private Worker $worker;

    private LogStorage $logs;

    private function __construct()
    {
        $this->manager = new QueueManager();
        $this->dispatcher = new Dispatcher($this->manager);
        $this->scheduler = new Scheduler();
        $this->logs = new LogStorage();
        $this->worker = new Worker($this->manager);
        $this->worker->setLogger($this->logs);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Boot the queue system.
     */
    public static function boot(): void
    {
        $instance = self::getInstance();

        // Allow plugins to register scheduled jobs
        add_action('init', static function () use ($instance): void {
            do_action('wp_queue_schedule', $instance->scheduler);
            $instance->scheduler->register();
        });

        // Register cron handler for processing queue
        add_action('wp_queue_process', static function (string $queue = 'default'): void {
            $instance = self::getInstance();
            $instance->worker->setMaxJobs(10);
            $instance->worker->setMaxTime(20);

            while (! $instance->worker->shouldStop()) {
                if (! $instance->worker->runNextJob($queue)) {
                    break;
                }
            }
        });

        // Schedule queue processing
        add_action('init', static function (): void {
            if (! wp_next_scheduled('wp_queue_process')) {
                wp_schedule_event(time(), 'min', 'wp_queue_process');
            }
        });

        // Admin UI
        if (is_admin()) {
            new AdminPage();
        }

        // REST API (must be outside is_admin() for REST requests to work)
        new RestApi();

        // WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            self::registerCliCommands();
        }

        // Load translations
        add_action('init', static function (): void {
            load_plugin_textdomain('wp-queue', false, dirname(plugin_basename(WP_QUEUE_FILE)).'/languages');
        });
    }

    /**
     * Register WP-CLI commands.
     */
    protected static function registerCliCommands(): void
    {
        \WP_CLI::add_command('queue', CLI\QueueCommand::class);
        \WP_CLI::add_command('queue cron', CLI\CronCommand::class);
    }

    /**
     * Plugin activation.
     */
    public static function activate(): void
    {
        // Schedule queue processing
        if (! wp_next_scheduled('wp_queue_process')) {
            wp_schedule_event(time(), 'min', 'wp_queue_process');
        }
    }

    /**
     * Plugin deactivation.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('wp_queue_process');
    }

    /**
     * Dispatch a job to the queue.
     */
    public static function dispatch(JobInterface $job): PendingDispatch
    {
        return self::getInstance()->dispatcher->dispatch($job);
    }

    /**
     * Dispatch a job synchronously.
     */
    public static function dispatchSync(JobInterface $job): void
    {
        self::getInstance()->dispatcher->dispatchSync($job);
    }

    /**
     * Dispatch a job immediately.
     */
    public static function dispatchNow(JobInterface $job): void
    {
        self::getInstance()->dispatcher->dispatchNow($job);
    }

    /**
     * Chain multiple jobs.
     *
     * @param  JobInterface[]  $jobs
     */
    public static function chain(array $jobs): PendingChain
    {
        return self::getInstance()->dispatcher->chain($jobs);
    }

    /**
     * Batch multiple jobs.
     *
     * @param  JobInterface[]  $jobs
     */
    public static function batch(array $jobs): PendingBatch
    {
        return self::getInstance()->dispatcher->batch($jobs);
    }

    /**
     * Get the scheduler.
     */
    public static function scheduler(): Scheduler
    {
        return self::getInstance()->scheduler;
    }

    /**
     * Get the queue manager.
     */
    public static function manager(): QueueManager
    {
        return self::getInstance()->manager;
    }

    /**
     * Get log storage.
     */
    public static function logs(): LogStorage
    {
        return self::getInstance()->logs;
    }

    /**
     * Get worker instance.
     */
    public static function worker(): Worker
    {
        return self::getInstance()->worker;
    }

    /**
     * Check if a queue is processing.
     */
    public static function isProcessing(string $queue = 'default'): bool
    {
        return (bool) get_site_transient('wp_queue_lock_'.$queue);
    }

    /**
     * Get queue size.
     */
    public static function queueSize(string $queue = 'default'): int
    {
        return self::getInstance()->manager->connection()->size($queue);
    }

    /**
     * Pause a queue.
     */
    public static function pause(string $queue = 'default'): void
    {
        update_site_option('wp_queue_status_'.$queue, 'paused');
    }

    /**
     * Resume a queue.
     */
    public static function resume(string $queue = 'default'): void
    {
        delete_site_option('wp_queue_status_'.$queue);
    }

    /**
     * Check if queue is paused.
     */
    public static function isPaused(string $queue = 'default'): bool
    {
        return get_site_option('wp_queue_status_'.$queue) === 'paused';
    }

    /**
     * Clear all jobs from a queue.
     */
    public static function clear(string $queue = 'default'): int
    {
        return self::getInstance()->manager->connection()->clear($queue);
    }

    /**
     * Cancel a queue (clear and pause).
     */
    public static function cancel(string $queue = 'default'): void
    {
        self::clear($queue);
        self::pause($queue);
    }
}
