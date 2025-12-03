<?php

declare(strict_types=1);

namespace WPQueue\Events;

use Throwable;
use WPQueue\Contracts\JobInterface;

final readonly class JobFailed
{
    public function __construct(
        public JobInterface $job,
        public string $queue,
        public Throwable $exception,
    ) {}
}
