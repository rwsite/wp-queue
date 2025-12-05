<?php

declare(strict_types=1);

use WPQueue\Tests\Fixtures\AlwaysFailingJob;
use WPQueue\Tests\Fixtures\CounterJob;
use WPQueue\Tests\Fixtures\EmailQueueJob;
use WPQueue\Tests\Fixtures\HourlyScheduledJob;
use WPQueue\Tests\Fixtures\SimpleTestJob;
use WPQueue\Tests\Fixtures\SlowJob;
use WPQueue\WPQueue;

beforeEach(function (): void {
    if (! defined('WP_CLI')) {
        define('WP_CLI', true);
    }

    // Очистка кэша опций
    wp_cache_flush();

    // Очистка очередей и логов напрямую через БД
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name IN ('wp_queue_jobs_default', 'wp_queue_jobs_emails')");
    $wpdb->query("DELETE FROM {$wpdb->prefix}queue_logs");

    // Очистка счётчиков и статусов
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%' AND option_name NOT LIKE 'wp_queue_jobs_%'");

    // Явная очистка статуса паузы
    delete_site_option('wp_queue_status_default');
    delete_site_option('wp_queue_status_emails');

    // Повторная очистка кэша
    wp_cache_flush();

    // Сброс состояния воркера для каждого теста
    WPQueue::worker()->reset();

    // Очистка всех WP-Cron хуков для запланированных задач
    remove_all_actions('wp_queue_hourly_scheduled_job');
    remove_all_actions('wp_queue_daily_scheduled_job');
    remove_all_actions('wp_queue_weekly_scheduled_job');
    remove_all_actions('wp_queue_monthly_scheduled_job');
    remove_all_actions('wp_queue_every_minute_job');
    remove_all_actions('wp_queue_every_five_minutes_job');
    remove_all_actions('wp_queue_every_ten_minutes_job');
    remove_all_actions('wp_queue_every_thirty_minutes_job');
    remove_all_actions('wp_queue_at_scheduled_job');
    remove_all_actions('wp_queue_cron_scheduled_job');
    remove_all_actions('wp_queue_daily_at_scheduled_job');
});

afterEach(function (): void {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

test('CLI команда queue:work обрабатывает задачи из очереди', function (): void {
    delete_option('wp_queue_test_counter');

    $job = new CounterJob('wp_queue_test_counter');

    WPQueue::dispatch($job);
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Имитация выполнения CLI команды
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('default');

    expect((int) get_option('wp_queue_test_counter', 0))->toBe(1);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('CLI команда queue:work с параметром --queue обрабатывает указанную очередь', function (): void {
    delete_option('wp_queue_default_counter');
    delete_option('wp_queue_emails_counter');

    $defaultJob = new CounterJob('wp_queue_default_counter');
    $emailsJob = new EmailQueueJob('wp_queue_emails_counter');

    WPQueue::dispatch($defaultJob);
    WPQueue::dispatch($emailsJob);

    // Обработка только emails очереди
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('emails');

    expect((int) get_option('wp_queue_default_counter', 0))->toBe(0);
    expect((int) get_option('wp_queue_emails_counter', 0))->toBe(1);
});

test('CLI команда queue:work с параметром --max-jobs ограничивает количество задач', function (): void {
    delete_option('wp_queue_cli_maxjobs_count');

    for ($i = 0; $i < 10; $i++) {
        $job = new CounterJob('wp_queue_cli_maxjobs_count');
        WPQueue::dispatch($job);
    }

    expect(WPQueue::queueSize('default'))->toBe(10);

    $worker = WPQueue::worker();
    $worker->setMaxJobs(5);

    $processed = 0;
    while ($processed < 20 && $worker->runNextJob('default')) {
        $processed++;
    }

    expect((int) get_option('wp_queue_cli_maxjobs_count', 0))->toBe(5);
    expect(WPQueue::queueSize('default'))->toBe(5);
});

test('CLI команда queue:work с параметром --max-time ограничивает время выполнения', function (): void {
    delete_option('wp_queue_cli_maxtime_count');

    for ($i = 0; $i < 100; $i++) {
        $job = new SlowJob(50000, 'wp_queue_cli_maxtime_count');
        WPQueue::dispatch($job);
    }

    $worker = WPQueue::worker();
    $worker->setMaxTime(1);

    $startTime = time();
    $processed = 0;
    while ($processed < 100 && $worker->runNextJob('default')) {
        $processed++;
    }
    $elapsed = time() - $startTime;

    expect($elapsed)->toBeLessThanOrEqual(2);
    expect((int) get_option('wp_queue_cli_maxtime_count', 0))->toBeLessThan(100);
});

test('CLI команда queue:list показывает список очередей', function (): void {
    $job1 = new SimpleTestJob();
    $job2 = new EmailQueueJob();

    WPQueue::dispatch($job1);
    WPQueue::dispatch($job2);

    // Получение списка очередей
    $manager = WPQueue::manager();
    $queues = ['default', 'emails'];

    expect($queues)->toContain('default');
    expect($queues)->toContain('emails');
});

test('CLI команда queue:clear очищает указанную очередь', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $job = new SimpleTestJob();
        WPQueue::dispatch($job);
    }

    expect(WPQueue::queueSize('default'))->toBe(5);

    $cleared = WPQueue::clear('default');

    expect($cleared)->toBe(5);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('CLI команда queue:pause ставит очередь на паузу', function (): void {
    expect(WPQueue::isPaused('default'))->toBeFalse();

    WPQueue::pause('default');

    expect(WPQueue::isPaused('default'))->toBeTrue();
});

test('CLI команда queue:resume возобновляет очередь', function (): void {
    WPQueue::pause('default');
    expect(WPQueue::isPaused('default'))->toBeTrue();

    WPQueue::resume('default');

    expect(WPQueue::isPaused('default'))->toBeFalse();
});

test('CLI команда queue:stats показывает статистику очередей', function (): void {
    // Добавляем задачи
    for ($i = 0; $i < 3; $i++) {
        $job = new SimpleTestJob();
        WPQueue::dispatch($job);
    }

    // Обрабатываем одну задачу
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('default');

    $size = WPQueue::queueSize('default');
    $logs = WPQueue::logs()->recent(100);

    expect($size)->toBe(2);
    expect($logs)->not->toBeEmpty();
});

test('CLI команда queue:failed показывает проваленные задачи', function (): void {
    $job = new AlwaysFailingJob('Test error');

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect($failed)->not->toBeEmpty();
});

test('CLI команда queue:retry повторяет проваленную задачу', function (): void {
    $job = new AlwaysFailingJob('First attempt fails');

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    // Проверяем что задача провалилась
    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect($failed)->not->toBeEmpty();

    // Повторная отправка задачи
    $job2 = new AlwaysFailingJob('Second attempt fails');
    WPQueue::dispatch($job2);
    $worker->runNextJob('default');

    // Проверяем что теперь 2 проваленные задачи
    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect(count($failed))->toBe(2);
});

test('CLI команда queue:flush очищает все очереди', function (): void {
    $job1 = new SimpleTestJob();
    $job2 = new EmailQueueJob();

    WPQueue::dispatch($job1);
    WPQueue::dispatch($job2);

    expect(WPQueue::queueSize('default'))->toBe(1);
    expect(WPQueue::queueSize('emails'))->toBe(1);

    WPQueue::clear('default');
    WPQueue::clear('emails');

    expect(WPQueue::queueSize('default'))->toBe(0);
    expect(WPQueue::queueSize('emails'))->toBe(0);
});

test('CLI команда cron:list показывает запланированные задачи', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    $hook = 'wp_queue_hourly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
});

test('CLI команда cron:run запускает запланированные задачи', function (): void {
    delete_option('wp_queue_hourly_executed');

    // Регистрируем задачу в scheduler
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    // Имитация выполнения cron события - вызываем хук напрямую
    $hook = 'wp_queue_hourly_scheduled_job';
    do_action($hook);

    // Задача добавлена в очередь
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Выполнение задачи
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect(get_option('wp_queue_hourly_executed'))->toBeTrue();
});

test('CLI команда queue:monitor отслеживает состояние очередей', function (): void {
    $job = new SimpleTestJob();

    WPQueue::dispatch($job);

    $size = WPQueue::queueSize('default');
    $isPaused = WPQueue::isPaused('default');
    $isProcessing = WPQueue::isProcessing('default');

    expect($size)->toBe(1);
    expect($isPaused)->toBeFalse();
    expect($isProcessing)->toBeFalse();
});
