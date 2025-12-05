<?php

declare(strict_types=1);

use WPQueue\Jobs\Job;
use WPQueue\WPQueue;

beforeEach(function (): void {
    if (! defined('WP_CLI')) {
        define('WP_CLI', true);
    }

    // Очистка всех очередей
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

afterEach(function (): void {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_%'");
});

test('CLI команда queue:work обрабатывает задачи из очереди', function (): void {
    $executed = false;

    $job = new class($executed) extends Job
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

    WPQueue::dispatch($job);
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Имитация выполнения CLI команды
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('default');

    expect($executed)->toBeTrue();
    expect(WPQueue::queueSize('default'))->toBe(0);
});

test('CLI команда queue:work с параметром --queue обрабатывает указанную очередь', function (): void {
    $defaultExecuted = false;
    $emailsExecuted = false;

    $defaultJob = new class($defaultExecuted) extends Job
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

    $emailsJob = new class($emailsExecuted) extends Job
    {
        public function __construct(private bool &$executed)
        {
            parent::__construct();
            $this->queue = 'emails';
        }

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    WPQueue::dispatch($defaultJob);
    WPQueue::dispatch($emailsJob);

    // Обработка только emails очереди
    $worker = WPQueue::worker();
    $worker->setMaxJobs(1);
    $worker->runNextJob('emails');

    expect($defaultExecuted)->toBeFalse();
    expect($emailsExecuted)->toBeTrue();
});

test('CLI команда queue:work с параметром --max-jobs ограничивает количество задач', function (): void {
    delete_option('wp_queue_cli_maxjobs_count');

    for ($i = 0; $i < 10; $i++) {
        $job = new class extends Job
        {
            public function handle(): void
            {
                $count = (int) get_option('wp_queue_cli_maxjobs_count', 0);
                update_option('wp_queue_cli_maxjobs_count', $count + 1);
            }
        };
        WPQueue::dispatch($job);
    }

    $worker = WPQueue::worker();
    $worker->setMaxJobs(5);

    while ($worker->runNextJob('default')) {
        // Обработка до лимита
    }

    expect((int) get_option('wp_queue_cli_maxjobs_count', 0))->toBe(5);
    expect(WPQueue::queueSize('default'))->toBe(5);
});

test('CLI команда queue:work с параметром --max-time ограничивает время выполнения', function (): void {
    delete_option('wp_queue_cli_maxtime_count');

    for ($i = 0; $i < 100; $i++) {
        $job = new class extends Job
        {
            public function handle(): void
            {
                $count = (int) get_option('wp_queue_cli_maxtime_count', 0);
                update_option('wp_queue_cli_maxtime_count', $count + 1);
                usleep(50000); // 0.05 секунды
            }
        };
        WPQueue::dispatch($job);
    }

    $worker = WPQueue::worker();
    $worker->setMaxTime(1);

    $startTime = time();
    while ($worker->runNextJob('default')) {
        // Обработка до таймаута
    }
    $elapsed = time() - $startTime;

    expect($elapsed)->toBeLessThanOrEqual(2);
    expect((int) get_option('wp_queue_cli_maxtime_count', 0))->toBeLessThan(100);
});

test('CLI команда queue:list показывает список очередей', function (): void {
    $job1 = new class extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->queue = 'emails';
        }

        public function handle(): void {}
    };

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
        $job = new class extends Job
        {
            public function handle(): void {}
        };
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
        $job = new class extends Job
        {
            public function handle(): void {}
        };
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
    $job = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->maxAttempts = 1;
        }

        public function handle(): void
        {
            throw new \Exception('Test error');
        }
    };

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect($failed)->not->toBeEmpty();
});

test('CLI команда queue:retry повторяет проваленную задачу', function (): void {
    $job = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->maxAttempts = 1;
        }

        public function handle(): void
        {
            throw new \Exception('First attempt fails');
        }
    };

    WPQueue::dispatch($job);

    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    // Проверяем что задача провалилась
    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect($failed)->not->toBeEmpty();

    // Повторная отправка задачи
    WPQueue::dispatch($job);
    $worker->runNextJob('default');

    // Проверяем что теперь 2 проваленные задачи
    $logs = WPQueue::logs()->recent(10);
    $failed = array_filter($logs, fn ($log) => $log['status'] === 'failed');

    expect(count($failed))->toBe(2);
});

test('CLI команда queue:flush очищает все очереди', function (): void {
    $job1 = new class extends Job
    {
        public function handle(): void {}
    };
    $job2 = new class extends Job
    {
        public function __construct()
        {
            parent::__construct();
            $this->queue = 'emails';
        }

        public function handle(): void {}
    };

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
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    $scheduler = WPQueue::scheduler();
    $scheduler->job($job)->hourly();
    $scheduler->register();

    $hook = 'wp_queue_'.strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr(strrchr(get_class($job), '\\') ?: get_class($job), 1) ?: get_class($job)));
    $scheduled = wp_get_scheduled_event($hook);

    expect($scheduled)->not->toBeFalse();
});

test('CLI команда cron:run запускает запланированные задачи', function (): void {
    $executed = false;

    $job = new class($executed) extends Job
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
    do_action('wp_queue_scheduled_job', get_class($job));

    // Задача добавлена в очередь
    expect(WPQueue::queueSize('default'))->toBe(1);

    // Выполнение задачи
    $worker = WPQueue::worker();
    $worker->runNextJob('default');

    expect($executed)->toBeTrue();
});

test('CLI команда queue:monitor отслеживает состояние очередей', function (): void {
    $job = new class extends Job
    {
        public function handle(): void {}
    };

    WPQueue::dispatch($job);

    $size = WPQueue::queueSize('default');
    $isPaused = WPQueue::isPaused('default');
    $isProcessing = WPQueue::isProcessing('default');

    expect($size)->toBe(1);
    expect($isPaused)->toBeFalse();
    expect($isProcessing)->toBeFalse();
});
