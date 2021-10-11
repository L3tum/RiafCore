<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(PrivateEvent::class, 'onPrivate')]
class PrivateEventListener
{
    private function onPrivate(object $event): object
    {
        return $event;
    }
}
