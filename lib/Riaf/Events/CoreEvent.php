<?php

namespace Riaf\Events;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class CoreEvent implements StoppableEventInterface
{
    protected bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function setPropagationStopped(): void
    {
        $this->propagationStopped = true;
    }
}
