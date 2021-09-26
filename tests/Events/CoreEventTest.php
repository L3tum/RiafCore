<?php

declare(strict_types=1);

namespace Riaf\Events;

use PHPUnit\Framework\TestCase;
use Riaf\Core;

class CoreEventTest extends TestCase
{
    public function testReturnsIsNotSkipped(): void
    {
        // We use a BootEvent here since CoreEvent is abstract
        $event = new BootEvent($this->createMock(Core::class));
        self::assertFalse($event->isPropagationStopped());
    }

    public function testReturnsIsSkipped(): void
    {
        // We use a BootEvent here since CoreEvent is abstract
        $event = new BootEvent($this->createMock(Core::class));
        $event->setPropagationStopped();
        self::assertTrue($event->isPropagationStopped());
    }
}
