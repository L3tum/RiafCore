<?php

declare(strict_types=1);

namespace Riaf\Events;

use League\Event\EventDispatcher;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RequestEventTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public function testFiresEvent(): void
    {
        $event = new RequestEvent($this->createMock(ServerRequestInterface::class));
        $returnedEvent = $this->eventDispatcher->dispatch($event);
        self::assertInstanceOf(RequestEvent::class, $returnedEvent);
        self::assertInstanceOf(CoreEvent::class, $returnedEvent);
        self::assertSame($event, $returnedEvent);
    }

    public function testProvidesRequestToListener(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new RequestEvent($request);
        $this->eventDispatcher->subscribeTo(RequestEvent::class, static function ($event) use ($request): void {
            /* @var RequestEvent $event */
            self::assertInstanceOf(RequestEvent::class, $event);
            self::assertInstanceOf($request::class, $event->getRequest());
        });
        $this->eventDispatcher->dispatch($event);
    }

    public function testCanOverwriteRequestInListener(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new RequestEvent($request);
        $this->eventDispatcher->subscribeTo(RequestEvent::class, static function ($event): void {
            /* @var RequestEvent $event */
            $event->setRequest(new ServerRequest('GET', '/'));
        });
        /** @var RequestEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        self::assertNotSame($request, $event->getRequest());
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }
}
