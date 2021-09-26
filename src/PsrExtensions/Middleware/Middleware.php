<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Middleware;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Middleware
{
    public function __construct(private int $priority = 0)
    {
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
