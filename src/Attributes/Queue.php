<?php

declare(strict_types=1);

namespace WPQueue\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Queue
{
    public function __construct(
        public string $name = 'default',
    ) {}
}
