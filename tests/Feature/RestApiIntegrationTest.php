<?php

declare(strict_types=1);

use WPQueue\Tests\Fixtures\AlwaysFailingJob;
use WPQueue\Tests\Fixtures\CounterJob;
use WPQueue\Tests\Fixtures\EmailQueueJob;
use WPQueue\Tests\Fixtures\SimpleTestJob;
use WPQueue\WPQueue;

beforeEach(function (): void {
    // Очистка всех очередей и статусов
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");

    // Явная очистка статуса паузы
    delete_site_option('wp_queue_status_default');
    delete_site_option('wp_queue_status_emails');

    // Мок для REST API
    global $wp_rest_server;
    $wp_rest_server = new \WP_REST_Server();
    do_action('rest_api_init');
});

afterEach(function (): void {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");

    global $wp_rest_server;
    $wp_rest_server = null;
});

test('REST API endpoint /queues возвращает список очередей', function (): void {
    // Добавляем задачи в разные очереди
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new EmailQueueJob());

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/queues');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toBeArray();
    expect($data)->toHaveKey('default');
    expect($data)->toHaveKey('emails');
    expect($data['default']['size'])->toBe(1);
    expect($data['emails']['size'])->toBe(1);
});

test('REST API endpoint /queues/{queue} возвращает информацию о конкретной очереди', function (): void {
    WPQueue::dispatch(new SimpleTestJob());

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/queues/default');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toBeArray();
    expect($data['name'])->toBe('default');
    expect($data['size'])->toBe(1);
    expect($data['status'])->toBe('active');
});

test('REST API endpoint /queues/{queue}/pause ставит очередь на паузу', function (): void {
    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/pause');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);
    expect(WPQueue::isPaused('default'))->toBeTrue();

    $data = $response->get_data();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('paused');
});

test('REST API endpoint /queues/{queue}/resume возобновляет очередь', function (): void {
    WPQueue::pause('default');
    expect(WPQueue::isPaused('default'))->toBeTrue();

    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/resume');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);
    expect(WPQueue::isPaused('default'))->toBeFalse();

    $data = $response->get_data();
    expect($data['success'])->toBeTrue();
    expect($data['message'])->toContain('resumed');
});

test('REST API endpoint /queues/{queue}/clear очищает очередь', function (): void {
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new SimpleTestJob());
    expect(WPQueue::queueSize('default'))->toBe(2);

    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/clear');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);
    expect(WPQueue::queueSize('default'))->toBe(0);

    $data = $response->get_data();
    expect($data['success'])->toBeTrue();
    expect($data['cleared'])->toBe(2);
});

test('REST API endpoint /queues/{queue}/jobs возвращает список задач в очереди', function (): void {
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new SimpleTestJob());

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/queues/default/jobs');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toBeArray();
    expect($data)->toHaveCount(2);
    expect($data[0])->toHaveKey('id');
    expect($data[0])->toHaveKey('class');
    expect($data[0])->toHaveKey('attempts');
});

test('REST API endpoint /logs возвращает последние логи', function (): void {
    WPQueue::dispatch(new SimpleTestJob());

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/logs');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toBeArray();
    expect($data)->not->toBeEmpty();
    expect($data[0])->toHaveKey('status');
    expect($data[0])->toHaveKey('queue');
    expect($data[0])->toHaveKey('job_class');
});

test('REST API endpoint /logs с параметром limit ограничивает количество логов', function (): void {
    // Создаем несколько задач
    for ($i = 0; $i < 5; $i++) {
        WPQueue::dispatch(new SimpleTestJob());
    }

    $worker = WPQueue::worker();
    $worker->setMaxJobs(5);
    $processed = 0;
    while ($worker->runNextJob('default') && ++$processed < 10) {
        // Обработка всех задач (с защитой от бесконечного цикла)
    }

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/logs');
    $request->set_param('limit', 3);
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toHaveCount(3);
});

test('REST API endpoint /logs с параметром status фильтрует логи', function (): void {
    WPQueue::dispatch(new SimpleTestJob());
    WPQueue::dispatch(new AlwaysFailingJob('Test error'));

    $worker = WPQueue::worker();
    $worker->runNextJob('default');
    $worker->runNextJob('default');

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/logs');
    $request->set_param('status', 'failed');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->not->toBeEmpty();

    foreach ($data as $log) {
        expect($log['status'])->toBe('failed');
    }
});

test('REST API endpoint /stats возвращает статистику очередей', function (): void {
    // Добавляем задачи
    for ($i = 0; $i < 3; $i++) {
        WPQueue::dispatch(new SimpleTestJob());
    }

    // Обрабатываем одну задачу
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('default');

    $request = new \WP_REST_Request('GET', '/wp-queue/v1/stats');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data)->toBeArray();
    expect($data)->toHaveKey('total_queues');
    expect($data)->toHaveKey('total_jobs');
    expect($data)->toHaveKey('total_processed');
    expect($data['total_jobs'])->toBe(2);
    expect($data['total_processed'])->toBeGreaterThanOrEqual(1);
});

test('REST API требует аутентификацию для защищенных эндпоинтов', function (): void {
    // Мок неавторизованного пользователя
    wp_set_current_user(0);

    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/clear');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(401);
});

test('REST API проверяет права доступа для административных действий', function (): void {
    // Мок пользователя без прав администратора
    $user_id = wp_create_user('testuser', 'password', 'test@example.com');
    wp_set_current_user($user_id);

    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/clear');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(403);
});

test('REST API возвращает 404 для несуществующей очереди', function (): void {
    $request = new \WP_REST_Request('GET', '/wp-queue/v1/queues/nonexistent');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(404);
});

test('REST API endpoint /queues/{queue}/process запускает обработку очереди', function (): void {
    delete_option('wp_queue_test_counter');

    WPQueue::dispatch(new CounterJob('wp_queue_test_counter'));

    $request = new \WP_REST_Request('POST', '/wp-queue/v1/queues/default/process');
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    $data = $response->get_data();
    expect($data['success'])->toBeTrue();
    expect($data['processed'])->toBeGreaterThan(0);
    expect((int) get_option('wp_queue_test_counter', 0))->toBe(1);
});
