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
