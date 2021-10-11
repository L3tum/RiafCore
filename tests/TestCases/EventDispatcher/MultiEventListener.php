<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(TestEventDosDos::class, 'onTest')]
#[Listener(TestEventDos::class, 'onTestDos')]
class MultiEventListener extends BaseTestCase
{
    public function onTest(object $event): object
    {
        $this->recordCalled();

        return $event;
    }

    public function onTestDos(object $event): object
    {
        $this->recordCalled();

        return $event;
    }
}
