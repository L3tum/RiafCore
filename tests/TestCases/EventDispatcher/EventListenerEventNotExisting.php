<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener('does-not-exist', 'onExist')]
class EventListenerEventNotExisting
{
    public function onExist(object $event): object
    {
        return $event;
    }
}
