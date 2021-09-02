<?php

namespace Riaf\Events;

use League\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Riaf\Core;

class TerminateEventTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public function testFiresEvent(): void
    {
        $event = new TerminateEvent($this->createMock(Core::class), $this->createMock(ServerRequestInterface::class), $this->createMock(ResponseInterface::class));
        $returnedEvent = $this->eventDispatcher->dispatch($event);
        self::assertInstanceOf(TerminateEvent::class, $returnedEvent);
        self::assertInstanceOf(CoreEvent::class, $returnedEvent);
        self::assertSame($event, $returnedEvent);
    }

    public function testProvidesCoreAndRequestAndResponseToListener(): void
    {
        $core = $this->createMock(Core::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new TerminateEvent($core, $request, $response);
        $this->eventDispatcher->subscribeTo(TerminateEvent::class, static function ($event) use ($core, $response, $request) {
            /* @var TerminateEvent $event */
            self::assertInstanceOf(TerminateEvent::class, $event);
            self::assertInstanceOf($core::class, $event->getCore());
            self::assertInstanceOf($request::class, $event->getRequest());
            self::assertInstanceOf($response::class, $event->getResponse());
        });
        $this->eventDispatcher->dispatch($event);
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }
}
