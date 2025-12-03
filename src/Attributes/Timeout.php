<?php

declare(strict_types=1);

namespace WPQueue\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Timeout
{
    public function __construct(
        public int $seconds = 60,
    ) {}
}
