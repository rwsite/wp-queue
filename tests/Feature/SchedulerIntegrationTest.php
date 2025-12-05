<?php

declare(strict_types=1);

use WPQueue\Tests\Fixtures\AtScheduledJob;
use WPQueue\Tests\Fixtures\CronScheduledJob;
use WPQueue\Tests\Fixtures\DailyAtScheduledJob;
use WPQueue\Tests\Fixtures\DailyScheduledJob;
use WPQueue\Tests\Fixtures\EveryFiveMinutesJob;
use WPQueue\Tests\Fixtures\EveryMinuteJob;
use WPQueue\Tests\Fixtures\EveryTenMinutesJob;
use WPQueue\Tests\Fixtures\EveryThirtyMinutesJob;
use WPQueue\Tests\Fixtures\HourlyScheduledJob;
use WPQueue\Tests\Fixtures\MonthlyScheduledJob;
use WPQueue\Tests\Fixtures\WeeklyScheduledJob;
use WPQueue\WPQueue;

beforeEach(function (): void {
    // Очистка всех cron задач
    wp_clear_scheduled_hook('wp_queue_scheduled_job');

    // Очистка всех очередей
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

afterEach(function (): void {
    wp_clear_scheduled_hook('wp_queue_scheduled_job');

    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

test('регистрация задачи с расписанием', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    // Проверка что задача зарегистрирована в WP-Cron
    $hook = 'wp_queue_hourly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
});

test('задача с расписанием hourly выполняется каждый час', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    $hook = 'wp_queue_hourly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('hourly');
});

test('задача с расписанием daily выполняется каждый день', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(DailyScheduledJob::class)->daily();
    $scheduler->register();

    $hook = 'wp_queue_daily_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('daily');
});

test('задача с кастомным интервалом регистрируется корректно', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->interval('every_5_minutes', 300, 'Every 5 Minutes');

    $schedules = wp_get_schedules();

    expect($schedules)->toHaveKey('every_5_minutes');
    expect($schedules['every_5_minutes']['interval'])->toBe(300);
    expect($schedules['every_5_minutes']['display'])->toBe('Every 5 Minutes');
});

test('задача с кастомным интервалом выполняется по расписанию', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->interval('every_10_minutes', 600, 'Every 10 Minutes');
    $scheduler->job(EveryTenMinutesJob::class)->schedule('every_10_minutes');
    $scheduler->register();

    $hook = 'wp_queue_every_ten_minutes_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('every_10_minutes');
});

test('несколько задач с разными расписаниями регистрируются независимо', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->job(DailyScheduledJob::class)->daily();
    $scheduler->register();

    $scheduled1 = wp_get_scheduled_event('wp_queue_hourly_scheduled_job');
    $scheduled2 = wp_get_scheduled_event('wp_queue_daily_scheduled_job');

    expect($scheduled1)->not->toBeFalse();
    expect($scheduled2)->not->toBeFalse();
    expect($scheduled1->schedule)->toBe('hourly');
    expect($scheduled2->schedule)->toBe('daily');
});

test('запуск запланированной задачи добавляет её в очередь', function (): void {
    delete_option('wp_queue_hourly_executed');

    // Регистрируем задачу в scheduler
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    // Имитация выполнения cron события - вызываем хук напрямую
    $hook = 'wp_queue_hourly_scheduled_job';
    do_action($hook);

    // Задача должна быть добавлена в очередь
    expect(WPQueue::queueSize('default'))->toBeGreaterThanOrEqual(1);

    // Выполнение задачи из очереди
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect(get_option('wp_queue_hourly_executed'))->toBeTrue();
});

test('отмена запланированной задачи удаляет её из WP-Cron', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(HourlyScheduledJob::class)->hourly();
    $scheduler->register();

    $hook = 'wp_queue_hourly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);
    expect($scheduled)->not->toBeFalse();

    // Отмена задачи
    wp_clear_scheduled_hook($hook);

    $scheduled = wp_get_scheduled_event($hook);
    expect($scheduled)->toBeFalse();
});

test('задача с at() выполняется в указанное время', function (): void {
    $futureTime = time() + 3600; // Через час

    $scheduler = WPQueue::scheduler();
    $scheduler->job(AtScheduledJob::class)->at($futureTime);
    $scheduler->register();

    $hook = 'wp_queue_at_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->timestamp)->toBe($futureTime);
});

test('задача с cron выражением регистрируется корректно', function (): void {
    // Каждый понедельник в 9:00
    $scheduler = WPQueue::scheduler();
    $scheduler->job(CronScheduledJob::class)->cron('0 9 * * 1');
    $scheduler->register();

    $hook = 'wp_queue_cron_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
});

test('задача daily_at выполняется в указанное время каждый день', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(DailyAtScheduledJob::class)->dailyAt('14:30');
    $scheduler->register();

    $hook = 'wp_queue_daily_at_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('daily');
});

test('задача weekly выполняется каждую неделю', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(WeeklyScheduledJob::class)->weekly();
    $scheduler->register();

    $hook = 'wp_queue_weekly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('weekly');
});

test('задача monthly выполняется каждый месяц', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(MonthlyScheduledJob::class)->monthly();
    $scheduler->register();

    $hook = 'wp_queue_monthly_scheduled_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
});

test('задача everyMinute выполняется каждую минуту', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(EveryMinuteJob::class)->everyMinute();
    $scheduler->register();

    $hook = 'wp_queue_every_minute_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('min');
});

test('задача everyFiveMinutes выполняется каждые 5 минут', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(EveryFiveMinutesJob::class)->everyFiveMinutes();
    $scheduler->register();

    $hook = 'wp_queue_every_five_minutes_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('5min');
});

test('задача everyTenMinutes выполняется каждые 10 минут', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(EveryTenMinutesJob::class)->everyTenMinutes();
    $scheduler->register();

    $hook = 'wp_queue_every_ten_minutes_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('10min');
});

test('задача everyThirtyMinutes выполняется каждые 30 минут', function (): void {
    $scheduler = WPQueue::scheduler();
    $scheduler->job(EveryThirtyMinutesJob::class)->everyThirtyMinutes();
    $scheduler->register();

    $hook = 'wp_queue_every_thirty_minutes_job';
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('30min');
});
