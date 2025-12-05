<?php

declare(strict_types=1);

use WPQueue\Jobs\Job;
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
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->hourly();
    $scheduler->register();

    // Проверка что задача зарегистрирована в WP-Cron
    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
});

test('задача с расписанием hourly выполняется каждый час', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->hourly();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('hourly');
});

test('задача с расписанием daily выполняется каждый день', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->daily();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

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

    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler->job($job)->schedule('every_10_minutes');
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('every_10_minutes');
});

test('несколько задач с разными расписаниями регистрируются независимо', function (): void {
    $job1 = new class extends Job
    {
        public function handle(): void {}
    };

    $job2 = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job1)->hourly();
    $scheduler->job($job2)->daily();
    $scheduler->register();

    $scheduled1 = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job1)]);
    $scheduled2 = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job2)]);

    expect($scheduled1)->not->toBeFalse();
    expect($scheduled2)->not->toBeFalse();
    expect($scheduled1->schedule)->toBe('hourly');
    expect($scheduled2->schedule)->toBe('daily');
});

test('запуск запланированной задачи добавляет её в очередь', function (): void {
    $executed = false;

    $jobClass = new class($executed) extends Job
    {
        public function __construct(private bool &$executed)
        {
            parent::__construct();
        }

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    // Имитация выполнения cron события
    do_action('wp_queue_scheduled_job', get_class($jobClass));

    // Задача должна быть добавлена в очередь
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Выполнение задачи из очереди
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect($executed)->toBeTrue();
});

test('отмена запланированной задачи удаляет её из WP-Cron', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->hourly();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);
    expect($scheduled)->not->toBeFalse();

    // Отмена задачи
    wp_clear_scheduled_hook('wp_queue_scheduled_job', [get_class($job)]);

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);
    expect($scheduled)->toBeFalse();
});

test('задача с at() выполняется в указанное время', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $futureTime = time() + 3600; // Через час

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->at($futureTime);
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->timestamp)->toBe($futureTime);
});

test('задача с cron выражением регистрируется корректно', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    // Каждый понедельник в 9:00
    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->cron('0 9 * * 1');
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
});

test('задача daily_at выполняется в указанное время каждый день', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->dailyAt('14:30');
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('daily');
});

test('задача weekly выполняется каждую неделю', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->weekly();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('weekly');
});

test('задача monthly выполняется каждый месяц', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->monthly();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
});

test('задача everyMinute выполняется каждую минуту', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->interval('min', 60, 'Every Minute');
    $scheduler->job($job)->everyMinute();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('min');
});

test('задача everyFiveMinutes выполняется каждые 5 минут', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->interval('every_5_minutes', 300, 'Every 5 Minutes');
    $scheduler->job($job)->everyFiveMinutes();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('every_5_minutes');
});

test('задача everyTenMinutes выполняется каждые 10 минут', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->interval('every_10_minutes', 600, 'Every 10 Minutes');
    $scheduler->job($job)->everyTenMinutes();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('every_10_minutes');
});

test('задача everyThirtyMinutes выполняется каждые 30 минут', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->interval('every_30_minutes', 1800, 'Every 30 Minutes');
    $scheduler->job($job)->everyThirtyMinutes();
    $scheduler->register();

    $scheduled = wp_get_scheduled_event('wp_queue_scheduled_job', [get_class($job)]);

    expect($scheduled)->not->toBeFalse();
    expect($scheduled->schedule)->toBe('every_30_minutes');
});
