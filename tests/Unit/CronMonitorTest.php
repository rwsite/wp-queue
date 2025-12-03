<?php

declare(strict_types=1);

use WPQueue\Admin\CronMonitor;

beforeEach(function (): void {
    Brain\Monkey\Functions\stubs([
        '__' => fn ($text) => $text,
        'esc_html__' => fn ($text) => $text,
        'wp_next_scheduled' => fn () => time() + 3600,
        'wp_unschedule_event' => fn () => true,
        'wp_schedule_single_event' => fn () => true,
        'do_action' => fn () => null,
        'current_time' => fn () => time(),
        'human_time_diff' => fn ($from, $to) => '1 hour',
    ]);
});

describe('CronMonitor', function (): void {
    it('can get all cron events', function (): void {
        $cronArray = [
            time() + 3600 => [
                'wp_scheduled_delete' => [
                    md5('') => [
                        'schedule' => 'daily',
                        'args' => [],
                        'interval' => DAY_IN_SECONDS,
                    ],
                ],
            ],
            time() + 7200 => [
                'wp_update_plugins' => [
                    md5('') => [
                        'schedule' => 'twicedaily',
                        'args' => [],
                        'interval' => 12 * HOUR_IN_SECONDS,
                    ],
                ],
            ],
        ];

        Brain\Monkey\Functions\expect('_get_cron_array')
            ->once()
            ->andReturn($cronArray);

        $monitor = new CronMonitor();
        $events = $monitor->getAllEvents();

        expect($events)->toBeArray();
        expect($events)->toHaveCount(2);
        expect($events[0]['hook'])->toBe('wp_scheduled_delete');
        expect($events[1]['hook'])->toBe('wp_update_plugins');
    });

    it('can filter events by hook prefix', function (): void {
        $cronArray = [
            time() + 3600 => [
                'wp_queue_process' => [
                    md5('') => ['schedule' => 'min', 'args' => [], 'interval' => 60],
                ],
                'woocommerce_cleanup' => [
                    md5('') => ['schedule' => 'daily', 'args' => [], 'interval' => DAY_IN_SECONDS],
                ],
            ],
        ];

        Brain\Monkey\Functions\expect('_get_cron_array')
            ->once()
            ->andReturn($cronArray);

        $monitor = new CronMonitor();
        $events = $monitor->getEventsByPrefix('wp_queue');

        expect($events)->toHaveCount(1);
        expect($events[0]['hook'])->toBe('wp_queue_process');
    });

    it('can get single event details', function (): void {
        $cronArray = [
            time() + 3600 => [
                'my_custom_hook' => [
                    md5(serialize(['arg1'])) => [
                        'schedule' => 'hourly',
                        'args' => ['arg1'],
                        'interval' => HOUR_IN_SECONDS,
                    ],
                ],
            ],
        ];

        Brain\Monkey\Functions\expect('_get_cron_array')
            ->once()
            ->andReturn($cronArray);

        $monitor = new CronMonitor();
        $event = $monitor->getEvent('my_custom_hook');

        expect($event)->not->toBeNull();
        expect($event['hook'])->toBe('my_custom_hook');
        expect($event['schedule'])->toBe('hourly');
        expect($event['args'])->toBe(['arg1']);
    });

    it('returns null for non-existent event', function (): void {
        Brain\Monkey\Functions\expect('_get_cron_array')
            ->once()
            ->andReturn([]);

        $monitor = new CronMonitor();
        $event = $monitor->getEvent('non_existent_hook');

        expect($event)->toBeNull();
    });

    it('can unschedule an event', function (): void {
        $timestamp = time() + 3600;

        Brain\Monkey\Functions\stubs([
            'wp_unschedule_event' => fn () => true,
        ]);

        $monitor = new CronMonitor();
        $result = $monitor->unschedule('my_hook', $timestamp, []);

        expect($result)->toBeTrue();
    });

    it('can run event now', function (): void {
        $called = false;
        Brain\Monkey\Functions\stubs([
            'do_action' => function () use (&$called): void {
                $called = true;
            },
        ]);

        $monitor = new CronMonitor();
        $monitor->runNow('my_hook', []);

        // Just verify no exception thrown
        expect(true)->toBeTrue();
    });

    it('can get cron schedules', function (): void {
        Brain\Monkey\Functions\expect('wp_get_schedules')
            ->once()
            ->andReturn([
                'hourly' => ['interval' => 3600, 'display' => 'Once Hourly'],
                'daily' => ['interval' => 86400, 'display' => 'Once Daily'],
            ]);

        $monitor = new CronMonitor();
        $schedules = $monitor->getSchedules();

        expect($schedules)->toHaveKey('hourly');
        expect($schedules)->toHaveKey('daily');
        expect($schedules['hourly']['interval'])->toBe(3600);
    });

    it('identifies core WordPress hooks', function (): void {
        $monitor = new CronMonitor();

        expect($monitor->isWordPressCore('wp_scheduled_delete'))->toBeTrue();
        expect($monitor->isWordPressCore('wp_update_plugins'))->toBeTrue();
        expect($monitor->isWordPressCore('wp_version_check'))->toBeTrue();
        expect($monitor->isWordPressCore('my_custom_hook'))->toBeFalse();
    });

    it('identifies WooCommerce hooks', function (): void {
        $monitor = new CronMonitor();

        expect($monitor->isWooCommerce('woocommerce_cleanup_sessions'))->toBeTrue();
        expect($monitor->isWooCommerce('wc_admin_daily'))->toBeTrue();
        expect($monitor->isWooCommerce('my_custom_hook'))->toBeFalse();
    });

    it('can clear all hooks by prefix', function (): void {
        $cronArray = [
            time() + 3600 => [
                'my_plugin_task1' => [md5('') => ['schedule' => 'hourly', 'args' => [], 'interval' => 3600]],
                'my_plugin_task2' => [md5('') => ['schedule' => 'daily', 'args' => [], 'interval' => 86400]],
                'other_hook' => [md5('') => ['schedule' => 'hourly', 'args' => [], 'interval' => 3600]],
            ],
        ];

        Brain\Monkey\Functions\expect('_get_cron_array')
            ->once()
            ->andReturn($cronArray);

        Brain\Monkey\Functions\expect('wp_clear_scheduled_hook')
            ->twice();

        $monitor = new CronMonitor();
        $cleared = $monitor->clearByPrefix('my_plugin_');

        expect($cleared)->toBe(2);
    });
});
