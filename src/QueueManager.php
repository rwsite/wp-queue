<?php

declare(strict_types=1);

namespace WPQueue;

use InvalidArgumentException;
use WPQueue\Contracts\QueueInterface;
use WPQueue\Queue\DatabaseQueue;
use WPQueue\Queue\SyncQueue;

class QueueManager
{
    /**
     * @var array<string, QueueInterface>
     */
    protected array $connections = [];

    /**
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    protected string $defaultDriver = 'database';

    public function connection(?string $name = null): QueueInterface
    {
        $name ??= $this->getDefaultDriver();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * @param  callable(QueueManager): QueueInterface  $callback
     */
    public function extend(string $driver, callable $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    protected function resolve(string $name): QueueInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this);
        }

        return match ($name) {
            'database' => new DatabaseQueue(),
            'sync' => new SyncQueue(),
            default => throw new InvalidArgumentException("Queue driver [{$name}] is not supported."),
        };
    }
}
