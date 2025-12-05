<?php

declare(strict_types=1);

use WPQueue\Tests\Fixtures\AlwaysFailingJob;
use WPQueue\Tests\Fixtures\CounterJob;
use WPQueue\Tests\Fixtures\DataJob;
use WPQueue\Tests\Fixtures\DelayedCounterJob;
use WPQueue\Tests\Fixtures\EmailQueueJob;
use WPQueue\Tests\Fixtures\OrderedJob;
use WPQueue\Tests\Fixtures\RetryableJob;
use WPQueue\Tests\Fixtures\SimpleTestJob;
use WPQueue\Tests\Fixtures\SlowJob;
use WPQueue\WPQueue;

beforeEach(function (): void {
    // Очистка очередей (но не счётчиков)
    WPQueue::clear('default');
    WPQueue::clear('emails');

    // Очистка счётчиков и статусов
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%' AND option_name NOT LIKE 'wp_queue_jobs_%'");

    // Явная очистка статуса паузы
    delete_site_option('wp_queue_status_default');
    delete_site_option('wp_queue_status_emails');
});

afterEach(function (): void {
    // Очистка после тестов
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

test('полный цикл: dispatch -> queue -> process -> complete', function (): void {
    delete_option('wp_queue_test_counter');
    $job = new CounterJob('wp_queue_test_counter');

    // 1. Отправка задачи в очередь
    WPQueue::dispatch($job);

    // 2. Проверка что задача в очереди
    expect(WPQueue::queueSize('default'))->toBe(1);

    // 3. Обработка очереди
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $result = $worker->runNextJob('default');

    // 4. Проверка выполнения
    expect($result)->toBeTrue();
    expect(WPQueue::queueSize('default'))->toBe(0);
    expect((int) get_option('wp_queue_test_counter', 0))->toBe(1);
});

test('задача с delay откладывается на указанное время', function (): void {
    $job = new DelayedCounterJob('wp_queue_delayed_test');
    $job->delay(60);
    WPQueue::dispatch($job);

    // Задача в очереди, но не должна выполниться сразу
    expect(WPQueue::queueSize('default'))->toBe(1);

    $worker = WPQueue::worker();
    $result = $worker->runNextJob('default');

    // Задача не выполнена из-за delay
    expect($result)->toBeFalse();
    expect(WPQueue::queueSize('default'))->toBe(1);
});

test('задача с несколькими попытками при ошибке', function (): void {
    RetryableJob::$globalAttempts = 0;
    $job = new RetryableJob(5); // Падает 5 раз
    $job->setMaxAttempts(3);

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();

    // Попытка 1
    $worker->runNextJob('default');
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Попытка 2
    $worker->runNextJob('default');
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Попытка 3 (последняя)
    $worker->runNextJob('default');
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('несколько задач обрабатываются по порядку FIFO', function (): void {
    delete_option('wp_queue_execution_order');

    $job1 = new OrderedJob(1, 'wp_queue_execution_order');
    $job2 = new OrderedJob(2, 'wp_queue_execution_order');
    $job3 = new OrderedJob(3, 'wp_queue_execution_order');

    WPQueue::dispatch($job1);
    WPQueue::dispatch($job2);
    WPQueue::dispatch($job3);

    expect(WPQueue::queueSize('default'))->toBe(3);

    $worker = WPQueue::worker();
    $worker->setMaxJobs(3);

    $processed = 0;
    while ($processed < 10 && $worker->runNextJob('default')) {
        $processed++;
    }

    expect(get_option('wp_queue_execution_order'))->toBe([1, 2, 3]);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('разные очереди обрабатываются независимо', function (): void {
    delete_option('wp_queue_default_counter');
    delete_option('wp_queue_emails_counter');

    $defaultJob = new CounterJob('wp_queue_default_counter');
    $emailJob = new EmailQueueJob('wp_queue_emails_counter');

    WPQueue::dispatch($defaultJob);
    WPQueue::dispatch($emailJob);

    expect(WPQueue::queueSize('default'))->toBe(1);
    expect(WPQueue::queueSize('emails'))->toBe(1);

    // Обработка только default очереди
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect((int) get_option('wp_queue_default_counter', 0))->toBe(1);
    expect((int) get_option('wp_queue_emails_counter', 0))->toBe(0);
    expect(WPQueue::queueSize('default'))->toBe(0);
    expect(WPQueue::queueSize('emails'))->toBe(1);

    // Обработка emails очереди
    $worker->runNextJob('emails');

    expect((int) get_option('wp_queue_emails_counter', 0))->toBe(1);
    expect(WPQueue::queueSize('emails'))->toBe(0);
});

test('pause и resume очереди', function (): void {
    delete_option('wp_queue_pause_test');

    $job = new CounterJob('wp_queue_pause_test');

    WPQueue::dispatch($job);
    WPQueue::pause('default');

    expect(WPQueue::isPaused('default'))->toBeTrue();

    $worker = WPQueue::worker();
    $result = $worker->runNextJob('default');

    // Задача не выполнена, так как очередь на паузе
    expect($result)->toBeFalse();
    expect((int) get_option('wp_queue_pause_test', 0))->toBe(0);

    // Возобновление очереди
    WPQueue::resume('default');
    expect(WPQueue::isPaused('default'))->toBeFalse();

    $result = $worker->runNextJob('default');

    expect($result)->toBeTrue();
    expect((int) get_option('wp_queue_pause_test', 0))->toBe(1);
});

test('clear очищает все задачи из очереди', function (): void {
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new SimpleTestJob());

    expect(WPQueue::queueSize('default'))->toBe(3);

    $cleared = WPQueue::clear('default');

    expect($cleared)->toBe(3);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('cancel очищает и ставит очередь на паузу', function (): void {
    WPQueue::dispatch(new SimpleTestJob());
    expect(WPQueue::queueSize('default'))->toBe(1);

    WPQueue::cancel('default');

    expect(WPQueue::queueSize('default'))->toBe(0);
    expect(WPQueue::isPaused('default'))->toBeTrue();
});

test('dispatchSync выполняет задачу немедленно без очереди', function (): void {
    delete_option('wp_queue_sync_test');

    $job = new CounterJob('wp_queue_sync_test');

    WPQueue::dispatchSync($job);

    expect((int) get_option('wp_queue_sync_test', 0))->toBe(1);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('worker останавливается после maxJobs', function (): void {
    delete_option('wp_queue_maxjobs_count');

    for ($i = 0; $i < 10; $i++) {
        WPQueue::dispatch(new CounterJob('wp_queue_maxjobs_count'));
    }

    expect(WPQueue::queueSize('default'))->toBe(10);

    $worker = WPQueue::worker();
    $worker->setMaxJobs(5);

    $processed = 0;
    while ($processed < 20 && $worker->runNextJob('default')) {
        $processed++;
    }

    expect((int) get_option('wp_queue_maxjobs_count', 0))->toBe(5);
    expect(WPQueue::queueSize('default'))->toBe(5);
});

test('worker останавливается после maxTime', function (): void {
    delete_option('wp_queue_maxtime_count');

    for ($i = 0; $i < 100; $i++) {
        WPQueue::dispatch(new SlowJob(100000, 'wp_queue_maxtime_count'));
    }

    $worker = WPQueue::worker();
    $worker->setMaxTime(1); // 1 секунда

    $startTime = time();
    $processed = 0;
    while ($processed < 100 && $worker->runNextJob('default')) {
        $processed++;
    }
    $elapsed = time() - $startTime;

    expect($elapsed)->toBeLessThanOrEqual(2);
    expect((int) get_option('wp_queue_maxtime_count', 0))->toBeLessThan(100);
});

test('логирование успешного выполнения задачи', function (): void {
    WPQueue::dispatch(new SimpleTestJob());

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(1);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['status'])->toBe('completed');
    expect($logs[0]['queue'])->toBe('default');
});

test('логирование ошибки выполнения задачи', function (): void {
    WPQueue::dispatch(new AlwaysFailingJob('Test error message'));

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(1);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['status'])->toBe('failed');
    expect($logs[0]['message'])->toContain('Test error message');
});

test('задача с пользовательскими данными сериализуется корректно', function (): void {
    $testData = ['name' => 'Test', 'value' => 123];
    delete_option('wp_queue_data_job_result');

    $job = new DataJob($testData);

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect(get_option('wp_queue_data_job_result'))->toBe($testData);
});
