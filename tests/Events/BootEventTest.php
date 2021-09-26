<?php

declare(strict_types=1);

namespace Riaf\Events;

use League\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Riaf\Core;

class BootEventTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public function testFiresEvent(): void
    {
        $event = new BootEvent($this->createMock(Core::class));
        $returnedEvent = $this->eventDispatcher->dispatch($event);
        self::assertInstanceOf(BootEvent::class, $returnedEvent);
        self::assertInstanceOf(CoreEvent::class, $returnedEvent);
        self::assertSame($event, $returnedEvent);
    }

    public function testProvidesCoreToListener(): void
    {
        $core = $this->createMock(Core::class);
        $event = new BootEvent($core);
        $this->eventDispatcher->subscribeTo(BootEvent::class, static function ($event) use ($core): void {
            /* @var BootEvent $event */
            self::assertInstanceOf(BootEvent::class, $event);
            self::assertInstanceOf($core::class, $event->getCore());
        });
        $this->eventDispatcher->dispatch($event);
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }
}
