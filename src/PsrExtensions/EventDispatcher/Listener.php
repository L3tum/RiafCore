<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\EventDispatcher;

use Attribute;
use JetBrains\PhpStorm\ExpectedValues;
use Riaf\Events\CoreEvent;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(#[ExpectedValues(flagsFromClass: CoreEvent::class)] private string $target, private string $method)
    {
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
