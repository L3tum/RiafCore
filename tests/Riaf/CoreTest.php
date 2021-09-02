<?php

namespace Riaf;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Riaf\ResponseEmitter\ResponseEmitterInterface;

class CoreTest extends TestCase
{
    private MockObject|ContainerInterface $container;
    private MockObject|RequestHandlerInterface $middlewareDispatcher;
    private Core $core;

    public function testDoesNotFailWithoutEventDispatcherAndResponseEmitter(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = new Response();
        $this->middlewareDispatcher
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);
        $returnedResponse = $this->core->handle($request);
        self::assertSame($response, $returnedResponse);
    }

    public function testCallsRespectiveClassesIfGiven(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = new Response();
        $this->middlewareDispatcher
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(5))->method('log');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnArgument(0);
        $responseEmitter = $this->createMock(ResponseEmitterInterface::class);
        $responseEmitter
            ->expects($this->once())
            ->method('emitResponse');
        $this->container
            ->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive([EventDispatcherInterface::class], [ResponseEmitterInterface::class], [LoggerInterface::class])
            ->willReturnOnConsecutiveCalls($eventDispatcher, $responseEmitter, $logger);
        $this->container
            ->expects($this->exactly(3))
            ->method('has')
            ->withConsecutive([EventDispatcherInterface::class], [ResponseEmitterInterface::class], [LoggerInterface::class])
            ->willReturn(true);
        $this->core = new Core($this->container, $this->middlewareDispatcher);
        $this->core->handle($request);
    }

    public function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->middlewareDispatcher = $this->createMock(RequestHandlerInterface::class);
        $this->core = new Core($this->container, $this->middlewareDispatcher);
    }
}
