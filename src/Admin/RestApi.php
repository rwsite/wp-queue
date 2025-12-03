<?php

declare(strict_types=1);

namespace WPQueue\Admin;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPQueue\WPQueue;

class RestApi
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'wp-queue/v1';

        register_rest_route($namespace, '/queues', [
            'methods' => 'GET',
            'callback' => [$this, 'getQueues'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/queues/(?P<queue>[a-z0-9_-]+)/pause', [
            'methods' => 'POST',
            'callback' => [$this, 'pauseQueue'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/queues/(?P<queue>[a-z0-9_-]+)/resume', [
            'methods' => 'POST',
            'callback' => [$this, 'resumeQueue'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/queues/(?P<queue>[a-z0-9_-]+)/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clearQueue'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/jobs/(?P<job>[^/]+)/run', [
            'methods' => 'POST',
            'callback' => [$this, 'runJob'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'getLogs'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/logs/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clearLogs'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'getMetrics'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Cron endpoints
        register_rest_route($namespace, '/cron', [
            'methods' => 'GET',
            'callback' => [$this, 'getCronEvents'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/run', [
            'methods' => 'POST',
            'callback' => [$this, 'runCronEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'deleteCronEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/pause', [
            'methods' => 'POST',
            'callback' => [$this, 'pauseCronEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/resume', [
            'methods' => 'POST',
            'callback' => [$this, 'resumeCronEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/edit', [
            'methods' => 'POST',
            'callback' => [$this, 'editCronEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/cron/paused', [
            'methods' => 'GET',
            'callback' => [$this, 'getPausedCronEvents'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/system', [
            'methods' => 'GET',
            'callback' => [$this, 'getSystemStatus'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getQueues(): WP_REST_Response
    {
        global $wpdb;

        $queues = [];
        $results = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_jobs_%'"
        );

        foreach ($results as $optionName) {
            $queueName = str_replace('wp_queue_jobs_', '', $optionName);
            $jobs = get_site_option($optionName, []);

            $queues[$queueName] = [
                'size' => count($jobs),
                'paused' => WPQueue::isPaused($queueName),
                'processing' => WPQueue::isProcessing($queueName),
            ];
        }

        if (! isset($queues['default'])) {
            $queues['default'] = [
                'size' => 0,
                'paused' => WPQueue::isPaused('default'),
                'processing' => WPQueue::isProcessing('default'),
            ];
        }

        return new WP_REST_Response($queues);
    }

    public function pauseQueue(WP_REST_Request $request): WP_REST_Response
    {
        $queue = $request->get_param('queue');
        WPQueue::pause($queue);

        return new WP_REST_Response(['success' => true, 'message' => 'Queue paused']);
    }

    public function resumeQueue(WP_REST_Request $request): WP_REST_Response
    {
        $queue = $request->get_param('queue');
        WPQueue::resume($queue);

        return new WP_REST_Response(['success' => true, 'message' => 'Queue resumed']);
    }

    public function clearQueue(WP_REST_Request $request): WP_REST_Response
    {
        $queue = $request->get_param('queue');
        $cleared = WPQueue::clear($queue);

        return new WP_REST_Response(['success' => true, 'cleared' => $cleared]);
    }

    public function runJob(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $jobClass = urldecode($request->get_param('job'));

        if (! class_exists($jobClass)) {
            return new WP_Error('invalid_job', 'Job class not found', ['status' => 404]);
        }

        try {
            $job = new $jobClass();
            WPQueue::dispatch($job);

            return new WP_REST_Response(['success' => true, 'message' => 'Job dispatched']);
        } catch (\Throwable $e) {
            return new WP_Error('dispatch_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function getLogs(WP_REST_Request $request): WP_REST_Response
    {
        $filter = $request->get_param('filter') ?? 'all';
        $limit = (int) ($request->get_param('limit') ?? 100);

        $logs = match ($filter) {
            'failed' => WPQueue::logs()->failed(),
            'completed' => WPQueue::logs()->completed(),
            default => WPQueue::logs()->recent($limit),
        };

        return new WP_REST_Response($logs);
    }

    public function clearLogs(WP_REST_Request $request): WP_REST_Response
    {
        $days = (int) ($request->get_param('days') ?? 7);
        $cleared = WPQueue::logs()->clearOld($days);

        return new WP_REST_Response(['success' => true, 'cleared' => $cleared]);
    }

    public function getMetrics(): WP_REST_Response
    {
        return new WP_REST_Response(WPQueue::logs()->metrics());
    }

    public function getCronEvents(): WP_REST_Response
    {
        $monitor = new CronMonitor();

        return new WP_REST_Response([
            'events' => $monitor->getAllEvents(),
            'stats' => $monitor->getStats(),
            'schedules' => $monitor->getSchedules(),
        ]);
    }

    public function runCronEvent(WP_REST_Request $request): WP_REST_Response
    {
        $hook = $request->get_param('hook');
        $args = $request->get_param('args') ?? [];

        if (is_string($args)) {
            $args = json_decode($args, true) ?? [];
        }

        $monitor = new CronMonitor();
        $monitor->runNow($hook, $args);

        return new WP_REST_Response(['success' => true, 'message' => 'Cron event executed']);
    }

    public function deleteCronEvent(WP_REST_Request $request): WP_REST_Response
    {
        $hook = $request->get_param('hook');
        $timestamp = (int) $request->get_param('timestamp');
        $args = $request->get_param('args') ?? [];

        if (is_string($args)) {
            $args = json_decode($args, true) ?? [];
        }

        $monitor = new CronMonitor();
        $result = $monitor->unschedule($hook, $timestamp, $args);

        return new WP_REST_Response(['success' => $result]);
    }

    public function getSystemStatus(): WP_REST_Response
    {
        $status = new SystemStatus();

        return new WP_REST_Response([
            'report' => $status->getFullReport(),
            'health' => $status->getHealthStatus(),
        ]);
    }

    public function pauseCronEvent(WP_REST_Request $request): WP_REST_Response
    {
        $hook = $request->get_param('hook');
        $timestamp = (int) $request->get_param('timestamp');
        $args = $this->parseArgs($request->get_param('args'));

        $monitor = new CronMonitor();
        $result = $monitor->pause($hook, $timestamp, $args);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? __('Cron event paused', 'wp-queue') : __('Failed to pause event', 'wp-queue'),
        ]);
    }

    public function resumeCronEvent(WP_REST_Request $request): WP_REST_Response
    {
        $hook = $request->get_param('hook');
        $args = $this->parseArgs($request->get_param('args'));

        $monitor = new CronMonitor();
        $result = $monitor->resume($hook, $args);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? __('Cron event resumed', 'wp-queue') : __('Failed to resume event', 'wp-queue'),
        ]);
    }

    public function editCronEvent(WP_REST_Request $request): WP_REST_Response
    {
        $hook = $request->get_param('hook');
        $timestamp = (int) $request->get_param('timestamp');
        $args = $this->parseArgs($request->get_param('args'));
        $newSchedule = $request->get_param('schedule');
        $newTimestamp = $request->get_param('new_timestamp') ? (int) $request->get_param('new_timestamp') : null;

        $monitor = new CronMonitor();
        $result = $monitor->reschedule($hook, $timestamp, $args, $newSchedule, $newTimestamp);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? __('Cron event updated', 'wp-queue') : __('Failed to update event', 'wp-queue'),
        ]);
    }

    public function getPausedCronEvents(): WP_REST_Response
    {
        $monitor = new CronMonitor();

        return new WP_REST_Response($monitor->getPaused());
    }

    protected function parseArgs(mixed $args): array
    {
        if (is_string($args)) {
            return json_decode($args, true) ?? [];
        }

        return is_array($args) ? $args : [];
    }
}
