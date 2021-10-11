<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(ProtectedEvent::class, 'onPrivate')]
class ProtectedEventListener
{
    private function onPrivate(object $event): object
    {
        return $event;
    }
}
