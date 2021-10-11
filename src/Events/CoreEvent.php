<?php

declare(strict_types=1);

namespace Riaf\Events;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class CoreEvent implements StoppableEventInterface
{
    public const Boot = BootEvent::class;

    public const Request = RequestEvent::class;

    public const Response = ResponseEvent::class;

    public const Terminate = TerminateEvent::class;

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
