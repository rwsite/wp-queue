<?php

declare(strict_types=1);

namespace WPQueue\Tests\Fixtures;

use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;
use WPQueue\Attributes\Schedule;
use WPQueue\Attributes\Timeout;
use WPQueue\Jobs\Job;

/**
 * Простая задача для тестирования.
 */
class SimpleTestJob extends Job
{
    public bool $executed = false;

    public function handle(): void
    {
        $this->executed = true;
    }
}

/**
 * Задача с параметрами.
 */
class JobWithParameters extends Job
{
    public function __construct(
        public string $name,
        public int $value,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // Обработка с параметрами
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), [
            'name' => $this->name,
            'value' => $this->value,
        ]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->name = $data['name'];
        $this->value = $data['value'];
    }
}

/**
 * Задача которая всегда падает.
 */
#[Retries(3)]
class FailingJob extends Job
{
    public int $attempts = 0;

    public function handle(): void
    {
        $this->attempts++;
        throw new \Exception('This job always fails');
    }
}

/**
 * Задача с долгим выполнением.
 */
#[Timeout(30)]
class LongRunningJob extends Job
{
    public function __construct(public int $seconds = 5)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        sleep($this->seconds);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['seconds' => $this->seconds]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->seconds = $data['seconds'];
    }
}

/**
 * Задача для emails очереди.
 */
#[Queue('emails')]
class SendEmailJob extends Job
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $message,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // Отправка email
        wp_mail($this->to, $this->subject, $this->message);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), [
            'to' => $this->to,
            'subject' => $this->subject,
            'message' => $this->message,
        ]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->to = $data['to'];
        $this->subject = $data['subject'];
        $this->message = $data['message'];
    }
}

/**
 * Задача с расписанием.
 */
#[Schedule('hourly')]
class ScheduledJob extends Job
{
    public function handle(): void
    {
        // Выполняется каждый час
    }
}

/**
 * Задача с кастомным обработчиком ошибок.
 */
class JobWithErrorHandler extends Job
{
    public ?\Throwable $lastError = null;

    public function handle(): void
    {
        throw new \Exception('Test error');
    }

    public function failed(\Throwable $e): void
    {
        $this->lastError = $e;
        // Логирование, уведомление и т.д.
    }
}

/**
 * Задача для тестирования уникальности.
 */
class UniqueJob extends Job
{
    public function __construct(public string $key)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        // Обработка
    }

    public function uniqueId(): string
    {
        return $this->key;
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['key' => $this->key]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->key = $data['key'];
    }
}

/**
 * Задача для тестирования chain.
 */
class ChainableJob extends Job
{
    public static array $executionOrder = [];

    public function __construct(public int $step)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        self::$executionOrder[] = $this->step;
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['step' => $this->step]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->step = $data['step'];
    }
}

/**
 * Задача для тестирования batch.
 */
class BatchableJob extends Job
{
    public static int $completedCount = 0;

    public function handle(): void
    {
        self::$completedCount++;
    }
}

/**
 * Задача для E2E тестов - инкрементирует счётчик в опциях.
 */
class CounterJob extends Job
{
    public function __construct(
        public string $optionName = 'wp_queue_test_counter',
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $count = (int) get_option($this->optionName, 0);
        update_option($this->optionName, $count + 1);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['optionName' => $this->optionName]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->optionName = $data['optionName'] ?? 'wp_queue_test_counter';
    }
}

/**
 * Задача для E2E тестов - с задержкой.
 */
class DelayedCounterJob extends Job
{
    public function __construct(
        public string $optionName = 'wp_queue_delayed_counter',
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $count = (int) get_option($this->optionName, 0);
        update_option($this->optionName, $count + 1);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['optionName' => $this->optionName]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->optionName = $data['optionName'] ?? 'wp_queue_delayed_counter';
    }
}

/**
 * Задача для E2E тестов - всегда падает (для тестирования retry).
 */
class AlwaysFailingJob extends Job
{
    public function __construct(
        public string $message = 'Test error',
    ) {
        parent::__construct();
        $this->maxAttempts = 1;
    }

    public function handle(): void
    {
        throw new \Exception($this->message);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['message' => $this->message]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->message = $data['message'] ?? 'Test error';
    }
}

/**
 * Задача для E2E тестов - падает N раз, потом успешно.
 */
class RetryableJob extends Job
{
    public static int $globalAttempts = 0;

    public function __construct(
        public int $failTimes = 2,
    ) {
        parent::__construct();
        $this->maxAttempts = 5;
    }

    public function handle(): void
    {
        self::$globalAttempts++;
        if (self::$globalAttempts <= $this->failTimes) {
            throw new \Exception('Attempt '.self::$globalAttempts.' failed');
        }
        update_option('wp_queue_retryable_success', true);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['failTimes' => $this->failTimes]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->failTimes = $data['failTimes'] ?? 2;
    }
}

/**
 * Задача для E2E тестов - для очереди emails.
 */
#[Queue('emails')]
class EmailQueueJob extends Job
{
    public function __construct(
        public string $optionName = 'wp_queue_emails_counter',
    ) {
        parent::__construct();
        $this->queue = 'emails';
    }

    public function handle(): void
    {
        $count = (int) get_option($this->optionName, 0);
        update_option($this->optionName, $count + 1);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['optionName' => $this->optionName]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->optionName = $data['optionName'] ?? 'wp_queue_emails_counter';
    }
}

/**
 * Задача для E2E тестов - медленная (для тестирования max-time).
 */
class SlowJob extends Job
{
    public function __construct(
        public int $sleepMs = 50000,
        public string $optionName = 'wp_queue_slow_counter',
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        usleep($this->sleepMs);
        $count = (int) get_option($this->optionName, 0);
        update_option($this->optionName, $count + 1);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), [
            'sleepMs' => $this->sleepMs,
            'optionName' => $this->optionName,
        ]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->sleepMs = $data['sleepMs'] ?? 50000;
        $this->optionName = $data['optionName'] ?? 'wp_queue_slow_counter';
    }
}

/**
 * Задача для E2E тестов - записывает порядок выполнения.
 */
class OrderedJob extends Job
{
    public function __construct(
        public int $order = 0,
        public string $optionName = 'wp_queue_execution_order',
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $orders = get_option($this->optionName, []);
        $orders[] = $this->order;
        update_option($this->optionName, $orders);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), [
            'order' => $this->order,
            'optionName' => $this->optionName,
        ]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->order = $data['order'] ?? 0;
        $this->optionName = $data['optionName'] ?? 'wp_queue_execution_order';
    }
}

/**
 * Задача для E2E тестов - с пользовательскими данными.
 */
class DataJob extends Job
{
    public function __construct(
        public array $data = [],
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        update_option('wp_queue_data_job_result', $this->data);
    }

    public function __serialize(): array
    {
        return array_merge(parent::__serialize(), ['data' => $this->data]);
    }

    public function __unserialize(array $data): void
    {
        parent::__unserialize($data);
        $this->data = $data['data'] ?? [];
    }
}

/**
 * Задача для E2E тестов - для scheduler (hourly).
 */
#[Schedule('hourly')]
class HourlyScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_hourly_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler (daily).
 */
#[Schedule('daily')]
class DailyScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_daily_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler (weekly).
 */
class WeeklyScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_weekly_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler (monthly).
 */
class MonthlyScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_monthly_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler с at().
 */
class AtScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_at_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler с cron expression.
 */
class CronScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_cron_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler с dailyAt.
 */
class DailyAtScheduledJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_daily_at_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler everyMinute.
 */
class EveryMinuteJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_every_minute_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler everyFiveMinutes.
 */
class EveryFiveMinutesJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_every_5_min_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler everyTenMinutes.
 */
class EveryTenMinutesJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_every_10_min_executed', true);
    }
}

/**
 * Задача для E2E тестов - для scheduler everyThirtyMinutes.
 */
class EveryThirtyMinutesJob extends Job
{
    public function handle(): void
    {
        update_option('wp_queue_every_30_min_executed', true);
    }
}
