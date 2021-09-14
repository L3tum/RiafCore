<?php

declare(strict_types=1);

namespace Riaf;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Riaf\Compiler\SampleCompilerConfiguration;
use Riaf\ResponseEmitter\ResponseEmitterInterface;

class CoreTest extends TestCase
{
    private MockObject|ContainerInterface $container;

    private MockObject|RequestHandlerInterface $middlewareDispatcher;

    private Core $core;

    public function testCallsRespectiveClassesIfGiven(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = new Response();
        $this->middlewareDispatcher
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);
        $this->core->handle($request);
    }

    public function setUp(): void
    {
        $this->middlewareDispatcher = $this->createMock(RequestHandlerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(5))->method('debug');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnArgument(0);
        $responseEmitter = $this->createMock(ResponseEmitterInterface::class);
        $responseEmitter
            ->expects($this->once())
            ->method('emitResponse');
        $this->container = $this->createMock(ContainerInterface::class);
        $this->container
            ->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive([RequestHandlerInterface::class], [EventDispatcherInterface::class], [ResponseEmitterInterface::class], [LoggerInterface::class])
            ->willReturnOnConsecutiveCalls($this->middlewareDispatcher, $eventDispatcher, $responseEmitter, $logger);
        $this->container
            ->expects($this->exactly(4))
            ->method('has')
            ->withConsecutive([RequestHandlerInterface::class], [EventDispatcherInterface::class], [ResponseEmitterInterface::class], [LoggerInterface::class])
            ->willReturn(true);
        $this->core = new Core(new SampleCompilerConfiguration(), $this->container);
    }
}
