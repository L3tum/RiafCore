<?php

namespace Riaf\Events;

use League\Event\EventDispatcher;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ResponseEventTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    public function testFiresEvent(): void
    {
        $event = new ResponseEvent($this->createMock(ServerRequestInterface::class), $this->createMock(ResponseInterface::class));
        $returnedEvent = $this->eventDispatcher->dispatch($event);
        self::assertInstanceOf(ResponseEvent::class, $returnedEvent);
        self::assertInstanceOf(CoreEvent::class, $returnedEvent);
        self::assertSame($event, $returnedEvent);
    }

    public function testProvidesRequestAndResponseToListener(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new ResponseEvent($request, $response);
        $this->eventDispatcher->subscribeTo(ResponseEvent::class, static function ($event) use ($response, $request) {
            /* @var ResponseEvent $event */
            self::assertInstanceOf(ResponseEvent::class, $event);
            self::assertInstanceOf($request::class, $event->getRequest());
            self::assertInstanceOf($response::class, $event->getResponse());
        });
        $this->eventDispatcher->dispatch($event);
    }

    public function testCanOverwriteRequestAndResponseInListener(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $event = new ResponseEvent($request, $response);
        $this->eventDispatcher->subscribeTo(ResponseEvent::class, static function ($event) {
            /* @var ResponseEvent $event */
            $event->setRequest(new ServerRequest('GET', '/'));
            $event->setResponse(new Response());
        });
        /** @var ResponseEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        self::assertNotSame($request, $event->getRequest());
        self::assertNotSame($response, $event->getResponse());
    }

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }
}
