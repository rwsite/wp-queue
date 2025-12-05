<?php

declare(strict_types=1);

use WPQueue\Admin\SystemStatus;

beforeEach(function (): void {
    Brain\Monkey\Functions\stubs([
        '__' => fn ($text) => $text,
        'esc_html__' => fn ($text) => $text,
        'home_url' => fn () => 'https://example.com',
        'site_url' => fn () => 'http://example.com/wp-cron.php',
        'wp_remote_post' => fn () => ['response' => ['code' => 200]],
        'wp_remote_retrieve_response_code' => fn ($response) => $response['response']['code'] ?? 0,
        'is_wp_error' => fn ($thing) => false,
        'get_option' => fn ($key, $default = false) => $default,
        'size_format' => fn ($bytes) => round($bytes / 1024 / 1024, 2).' MB',
        'wp_date' => fn (string $format) => date($format),
    ]);
});

describe('SystemStatus', function (): void {
    it('can check if WP-Cron is disabled', function (): void {
        $status = new SystemStatus();

        // По умолчанию DISABLE_WP_CRON не определена
        expect($status->isWpCronDisabled())->toBeFalse();
    });

    it('can check alternate cron status', function (): void {
        $status = new SystemStatus();

        expect($status->isAlternateCron())->toBeFalse();
    });

    it('can get PHP memory limit', function (): void {
        $status = new SystemStatus();
        $limit = $status->getMemoryLimit();

        expect($limit)->toBeGreaterThan(0);
    });

    it('can get PHP max execution time', function (): void {
        $status = new SystemStatus();
        $time = $status->getMaxExecutionTime();

        expect($time)->toBeInt();
    });

    it('can get current memory usage', function (): void {
        $status = new SystemStatus();
        $usage = $status->getCurrentMemoryUsage();

        expect($usage)->toBeGreaterThan(0);
    });

    it('can get WordPress version', function (): void {
        global $wp_version;
        $wp_version = '6.4.2';

        $status = new SystemStatus();
        $version = $status->getWordPressVersion();

        expect($version)->toBe('6.4.2');
    });

    it('can get PHP version', function (): void {
        $status = new SystemStatus();
        $version = $status->getPhpVersion();

        expect($version)->toBe(PHP_VERSION);
    });

    it('can check loopback status', function (): void {
        Brain\Monkey\Functions\stubs([
            'admin_url' => fn () => 'http://example.com/wp-admin/admin-ajax.php',
            'apply_filters' => fn ($filter, $value) => $value,
            'wp_remote_post' => fn () => ['response' => ['code' => 200]],
            'is_wp_error' => fn () => false,
            'wp_remote_retrieve_response_code' => fn () => 200,
        ]);

        $status = new SystemStatus();
        $loopback = $status->checkLoopback();

        expect($loopback['status'])->toBe('ok');
    });

    it('detects unexpected response code as warning', function (): void {
        Brain\Monkey\Functions\stubs([
            'apply_filters' => fn ($filter, $value) => $value,
            'wp_remote_post' => fn () => ['response' => ['code' => 400]],
            'is_wp_error' => fn () => false,
            'wp_remote_retrieve_response_code' => fn () => 400,
        ]);

        $status = new SystemStatus();
        $loopback = $status->checkLoopback();

        expect($loopback['status'])->toBe('warning');
        expect($loopback['message'])->toContain('400');
    });

    it('detects loopback failure', function (): void {
        $wpError = new class
        {
            public function get_error_message(): string
            {
                return 'Connection refused';
            }
        };

        Brain\Monkey\Functions\stubs([
            'admin_url' => fn () => 'http://example.com/wp-admin/admin-ajax.php',
            'apply_filters' => fn ($filter, $value) => $value,
            'wp_remote_post' => fn () => $wpError,
            'is_wp_error' => fn ($thing) => is_object($thing) && method_exists($thing, 'get_error_message'),
        ]);

        $status = new SystemStatus();
        $loopback = $status->checkLoopback();

        expect($loopback['status'])->toBe('error');
        expect($loopback['message'])->toContain('Connection refused');
    });

    it('can get full system report', function (): void {
        Brain\Monkey\Functions\stubs([
            'admin_url' => fn () => 'http://example.com/wp-admin/admin-ajax.php',
            'apply_filters' => fn ($filter, $value) => $value,
            'wp_remote_post' => fn () => ['response' => ['code' => 200]],
            'is_wp_error' => fn () => false,
            'wp_remote_retrieve_response_code' => fn () => 200,
            'wp_timezone_string' => fn () => 'Europe/Moscow',
            'current_time' => fn () => date('Y-m-d H:i:s'),
        ]);

        global $wp_version;
        $wp_version = '6.4.2';

        $status = new SystemStatus();
        $report = $status->getFullReport();

        expect($report)->toHaveKey('php_version');
        expect($report)->toHaveKey('wp_version');
        expect($report)->toHaveKey('memory_limit');
        expect($report)->toHaveKey('max_execution_time');
        expect($report)->toHaveKey('current_memory');
        expect($report)->toHaveKey('wp_cron_disabled');
        expect($report)->toHaveKey('alternate_cron');
        expect($report)->toHaveKey('loopback');
    });

    it('can detect Action Scheduler', function (): void {
        $status = new SystemStatus();

        // Action Scheduler не загружен в тестах
        expect($status->hasActionScheduler())->toBeFalse();
    });

    it('can get timezone info', function (): void {
        Brain\Monkey\Functions\expect('wp_timezone_string')
            ->once()
            ->andReturn('Europe/Moscow');

        $status = new SystemStatus();
        $timezone = $status->getTimezone();

        expect($timezone)->toBe('Europe/Moscow');
    });
});
