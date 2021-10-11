<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;
use Riaf\TestCases\EventDispatcher\EventListenerEventNotExisting;
use Riaf\TestCases\EventDispatcher\EventListenerMethodNotExisting;
use Riaf\TestCases\EventDispatcher\MultiEventListener;
use Riaf\TestCases\EventDispatcher\PrivateEventListener;
use Riaf\TestCases\EventDispatcher\ProtectedEventListener;
use Riaf\TestCases\EventDispatcher\SingleEventListener;
use Riaf\TestCases\EventDispatcher\SingleStoppableEventListener;
use Riaf\TestCases\EventDispatcher\StaticEve;
use Riaf\TestCases\EventDispatcher\StaticMethodListener;
use Riaf\TestCases\EventDispatcher\TestEvent;
use Riaf\TestCases\EventDispatcher\TestEventDos;
use Riaf\TestCases\EventDispatcher\TestEventDosDos;
use Riaf\TestCases\EventDispatcher\TestStoppableEvent;
use RuntimeException;

class EventDispatcherTest extends TestCase
{
    public function testCallsSimpleEventListener(): void
    {
        $listener = new SingleEventListener();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')
            ->with(SingleEventListener::class)
            ->willReturn($listener);
        $dispatcher = $this->getEventDispatcher($container);
        $originalEvent = new TestEvent();
        $event = $dispatcher->dispatch($originalEvent);

        self::assertEquals($originalEvent, $event);
        self::assertEquals(1, $listener->getCalled(), 'Only called once');
    }

    public function getEventDispatcher(ContainerInterface $container): EventDispatcherInterface
    {
        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return new \Riaf\EventDispatcher($container);
    }

    public function testCallsSimpleEventListenerMultipleTimes(): void
    {
        $listener = new SingleEventListener();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))->method('get')
            ->with(SingleEventListener::class)
            ->willReturn($listener);
        $dispatcher = $this->getEventDispatcher($container);
        $originalEvent = new TestEvent();
        $event = $dispatcher->dispatch($originalEvent);
        $event = $dispatcher->dispatch($event);

        self::assertEquals($originalEvent, $event);
        self::assertEquals(2, $listener->getCalled(), 'Called exactly twice');
    }

    public function testDoesNotCallIfEventStoppedPropagation(): void
    {
        $listener = new SingleStoppableEventListener();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get')
            ->with(SingleStoppableEventListener::class)
            ->willReturn($listener);
        $dispatcher = $this->getEventDispatcher($container);
        $originalEvent = new TestStoppableEvent();
        $event = $dispatcher->dispatch($originalEvent);

        self::assertEquals($originalEvent, $event);
        self::assertEquals(0, $listener->getCalled(), 'Called never');
    }

    public function testCallsMultiEventListenerMultipleTimes(): void
    {
        $listener = new MultiEventListener();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))->method('get')
            ->with(MultiEventListener::class)
            ->willReturn($listener);
        $dispatcher = $this->getEventDispatcher($container);
        $originalEvent = new TestEventDosDos();
        $event = $dispatcher->dispatch($originalEvent);
        $originalEventDos = new TestEventDos();
        $eventDos = $dispatcher->dispatch($originalEventDos);

        self::assertEquals($originalEvent, $event);
        self::assertEquals($originalEventDos, $eventDos);
        self::assertEquals(2, $listener->getCalled(), 'Called exactly twice');
    }

    public function testCallsStaticEventListener(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');
        $dispatcher = $this->getEventDispatcher($container);
        $originalEvent = new StaticEve();
        $event = $dispatcher->dispatch($originalEvent);

        self::assertEquals($originalEvent, $event);
        self::assertEquals(1, StaticMethodListener::getCounter(), 'Called exactly once');
    }

    public function testThrowsIfEventDoesNotExist(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [EventListenerEventNotExisting::class];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);

        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testThrowsIfMethodDoesNotExist(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [EventListenerMethodNotExisting::class];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);

        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testThrowsIfClassDoesNotExist(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return ['Does-Not-Exist'];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);

        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testThrowsIfMethodIsPrivate(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [PrivateEventListener::class];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);

        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testThrowsIfMethodIsProtected(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [ProtectedEventListener::class];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);

        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function setUp(): void
    {
        // Why? Well, setUpBeforeClasses is not counted for coverage..
        if (class_exists('\\Riaf\\EventDispatcher', false)) {
            return;
        }

        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [SingleEventListener::class, MultiEventListener::class, StaticMethodListener::class];
            }
        };

        $compiler = new EventDispatcherCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);
        $compiler->supportsCompilation();
        $compiler->compile();

        $stream = $config->getFileHandle($compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        eval('?>' . $content);
    }
}
