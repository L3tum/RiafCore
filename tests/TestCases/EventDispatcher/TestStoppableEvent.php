<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

class TestStoppableEvent implements StoppableEventInterface
{
    public function isPropagationStopped(): bool
    {
        return true;
    }
}
