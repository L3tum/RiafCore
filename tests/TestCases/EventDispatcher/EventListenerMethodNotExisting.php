<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\Events\CoreEvent;
use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(CoreEvent::Boot, 'onNotExist')]
class EventListenerMethodNotExisting
{
    public function onExist(object $event): object
    {
        return $event;
    }
}
