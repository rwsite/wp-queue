<?php

declare(strict_types=1);

namespace WPQueue\Jobs;

use WPQueue\Contracts\JobInterface;
use WPQueue\Contracts\ShouldQueue;

abstract class Job implements JobInterface, ShouldQueue
{
    protected string $id;

    protected string $queue = 'default';

    protected int $attempts = 0;

    protected int $maxAttempts = 3;

    protected int $timeout = 60;

    protected int $delay = 0;

    protected int $createdAt;

    public function __construct()
    {
        $this->id = $this->generateId();
        $this->createdAt = time();
    }

    abstract public function handle(): void;

    public function getId(): string
    {
        return $this->id;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts(int $attempts): static
    {
        $this->maxAttempts = $attempts;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;

        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'class' => static::class,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'max_attempts' => $this->maxAttempts,
            'timeout' => $this->timeout,
            'delay' => $this->delay,
            'created_at' => $this->createdAt,
        ];
    }

    public function failed(\Throwable $e): void
    {
        // Override in child classes
    }

    protected function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->queue,
            'attempts' => $this->attempts,
            'maxAttempts' => $this->maxAttempts,
            'timeout' => $this->timeout,
            'delay' => $this->delay,
            'createdAt' => $this->createdAt,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->queue = $data['queue'];
        $this->attempts = $data['attempts'];
        $this->maxAttempts = $data['maxAttempts'];
        $this->timeout = $data['timeout'];
        $this->delay = $data['delay'];
        $this->createdAt = $data['createdAt'];
    }
}
