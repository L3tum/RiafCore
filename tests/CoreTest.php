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
        $config = new class() extends SampleCompilerConfiguration {
            public function getContainerNamespace(): string
            {
                return 'NotFound';
            }

            public function getRouterNamespace(): string
            {
                return 'NotFound';
            }

            public function getMiddlewareDispatcherNamespace(): string
            {
                return 'NotFound';
            }

            public function getContainerFilepath(): string
            {
                return 'NotFound';
            }

            public function getRouterFilepath(): string
            {
                return 'NotFound';
            }

            public function getEventDispatcherFilepath(): string
            {
                return 'NotFound';
            }

            public function getMiddlewareDispatcherFilepath(): string
            {
                return 'NotFound';
            }
        };

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
            ->expects($this->exactly(6))
            ->method('has')
            ->withConsecutive(["NotFound\MiddlewareDispatcher"], ["NotFound\Router"], [RequestHandlerInterface::class], [EventDispatcherInterface::class], [ResponseEmitterInterface::class], [LoggerInterface::class])
            ->willReturnOnConsecutiveCalls(false, false, true, true, true, true);
        $this->core = new Core(new $config(), $this->container);
    }
}
