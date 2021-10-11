<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(TestEvent::class, 'onTest')]
class SingleEventListener extends BaseTestCase
{
    public function onTest(TestEvent $event): TestEvent
    {
        $this->recordCalled();

        return $event;
    }
}
