<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(TestStoppableEvent::class, 'onTest')]
class SingleStoppableEventListener extends BaseTestCase
{
    public function onTest(object $event): object
    {
        $this->recordCalled();

        return $event;
    }
}
