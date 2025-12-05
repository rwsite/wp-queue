<?php

declare(strict_types=1);

use WPQueue\Jobs\Job;
use WPQueue\WPQueue;

beforeEach(function (): void {
    // Очистка всех очередей перед каждым тестом
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

afterEach(function (): void {
    // Очистка после тестов
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

test('полный цикл: dispatch -> queue -> process -> complete', function (): void {
    $job = new class extends Job
    {
        public bool $executed = false;

        public function handle(): void
        {
            $this->executed = true;
        }
    };

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
});

test('задача с delay откладывается на указанное время', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

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
    $job = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->maxAttempts = 3;
        }

        public function handle(): void
        {
            throw new \Exception('Test error');
        }
    };

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
    $job1 = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_fifo_test', array_merge(
                get_option('wp_queue_fifo_test', []),
                [1]
            ));
        }
    };

    $job2 = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_fifo_test', array_merge(
                get_option('wp_queue_fifo_test', []),
                [2]
            ));
        }
    };

    $job3 = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_fifo_test', array_merge(
                get_option('wp_queue_fifo_test', []),
                [3]
            ));
        }
    };

    delete_option('wp_queue_fifo_test');
    WPQueue::dispatch($job1);
    WPQueue::dispatch($job2);
    WPQueue::dispatch($job3);

    expect(WPQueue::queueSize('default'))->toBe(3);

    $worker = WPQueue::worker();
    $worker->setMaxJobs(3);

    while ($worker->runNextJob('default')) {
        // Обработка всех задач
    }

    expect(get_option('wp_queue_fifo_test'))->toBe([1, 2, 3]);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('разные очереди обрабатываются независимо', function (): void {
    delete_option('wp_queue_default_executed');
    delete_option('wp_queue_emails_executed');

    $defaultJob = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_default_executed', true);
        }
    };

    $emailJob = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->queue = 'emails';
        }

        public function handle(): void
        {
            update_option('wp_queue_emails_executed', true);
        }
    };

    WPQueue::dispatch($defaultJob);
    WPQueue::dispatch($emailJob);

    expect(WPQueue::queueSize('default'))->toBe(1);
    expect(WPQueue::queueSize('emails'))->toBe(1);

    // Обработка только default очереди
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect(get_option('wp_queue_default_executed'))->toBeTrue();
    expect(get_option('wp_queue_emails_executed'))->toBeFalsy();
    expect(WPQueue::queueSize('default'))->toBe(0);
    expect(WPQueue::queueSize('emails'))->toBe(1);

    // Обработка emails очереди
    $worker->runNextJob('emails');

    expect(get_option('wp_queue_emails_executed'))->toBeTrue();
    expect(WPQueue::queueSize('emails'))->toBe(0);
});

test('pause и resume очереди', function (): void {
    delete_option('wp_queue_pause_test');

    $job = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_pause_test', true);
        }
    };

    WPQueue::dispatch($job);
    WPQueue::pause('default');

    expect(WPQueue::isPaused('default'))->toBeTrue();

    $worker = WPQueue::worker();
    $result = $worker->runNextJob('default');

    // Задача не выполнена, так как очередь на паузе
    expect($result)->toBeFalse();
    expect(get_option('wp_queue_pause_test'))->toBeFalsy();

    // Возобновление очереди
    WPQueue::resume('default');
    expect(WPQueue::isPaused('default'))->toBeFalse();

    $result = $worker->runNextJob('default');

    expect($result)->toBeTrue();
    expect(get_option('wp_queue_pause_test'))->toBeTrue();
});

test('clear очищает все задачи из очереди', function (): void {
    $job1 = new class extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class extends Job
    {
        public function handle(): void {}
    };
    $job3 = new class extends Job
    {
        public function handle(): void {}
    };

    WPQueue::dispatch($job1);
    WPQueue::dispatch($job2);
    WPQueue::dispatch($job3);

    expect(WPQueue::queueSize('default'))->toBe(3);

    $cleared = WPQueue::clear('default');

    expect($cleared)->toBe(3);
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('cancel очищает и ставит очередь на паузу', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    WPQueue::dispatch($job);
    expect(WPQueue::queueSize('default'))->toBe(1);

    WPQueue::cancel('default');

    expect(WPQueue::queueSize('default'))->toBe(0);
    expect(WPQueue::isPaused('default'))->toBeTrue();
});

test('dispatchSync выполняет задачу немедленно без очереди', function (): void {
    delete_option('wp_queue_sync_test');

    $job = new class extends Job
    {
        public function handle(): void
        {
            update_option('wp_queue_sync_test', true);
        }
    };

    WPQueue::dispatchSync($job);

    expect(get_option('wp_queue_sync_test'))->toBeTrue();
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('worker останавливается после maxJobs', function (): void {
    delete_option('wp_queue_maxjobs_count');

    for ($i = 0; $i < 10; $i++) {
        $job = new class extends Job
        {
            public function handle(): void
            {
                $count = (int) get_option('wp_queue_maxjobs_count', 0);
                update_option('wp_queue_maxjobs_count', $count + 1);
            }
        };
        WPQueue::dispatch($job);
    }

    expect(WPQueue::queueSize('default'))->toBe(10);

    $worker = WPQueue::worker();
    $worker->setMaxJobs(5);

    while ($worker->runNextJob('default')) {
        // Обработка до лимита
    }

    expect((int) get_option('wp_queue_maxjobs_count', 0))->toBe(5);
    expect(WPQueue::queueSize('default'))->toBe(5);
});

test('worker останавливается после maxTime', function (): void {
    delete_option('wp_queue_maxtime_count');

    for ($i = 0; $i < 100; $i++) {
        $job = new class extends Job
        {
            public function handle(): void
            {
                $count = (int) get_option('wp_queue_maxtime_count', 0);
                update_option('wp_queue_maxtime_count', $count + 1);
                usleep(100000); // 0.1 секунды
            }
        };
        WPQueue::dispatch($job);
    }

    $worker = WPQueue::worker();
    $worker->setMaxTime(1); // 1 секунда

    $startTime = time();
    while ($worker->runNextJob('default')) {
        // Обработка до таймаута
    }
    $elapsed = time() - $startTime;

    expect($elapsed)->toBeLessThanOrEqual(2);
    expect((int) get_option('wp_queue_maxtime_count', 0))->toBeLessThan(100);
});

test('логирование успешного выполнения задачи', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(1);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['status'])->toBe('completed');
    expect($logs[0]['queue'])->toBe('default');
});

test('логирование ошибки выполнения задачи', function (): void {
    $job = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->maxAttempts = 1;
        }

        public function handle(): void
        {
            throw new \Exception('Test error message');
        }
    };

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(1);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['status'])->toBe('failed');
    expect($logs[0]['error'])->toContain('Test error message');
});

test('задача с пользовательскими данными сериализуется корректно', function (): void {
    $testData = ['name' => 'Test', 'value' => 123];
    delete_option('wp_queue_serialization_test');

    $job = new class($testData) extends Job
    {
        public function __construct(private array $data)
        {
            parent::__construct();
        }

        public function handle(): void
        {
            update_option('wp_queue_serialization_test', $this->data);
        }

        public function __serialize(): array
        {
            return array_merge(parent::__serialize(), ['data' => $this->data]);
        }

        public function __unserialize(array $data): void
        {
            parent::__unserialize($data);
            $this->data = $data['data'];
        }
    };

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect(get_option('wp_queue_serialization_test'))->toBe($testData);
});
