<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\TestCases\Middleware\NormalMiddleware;
use Riaf\TestCases\Middleware\PriorityMiddleware;

class MiddlewareDispatcherCompilerTest extends TestCase
{
    private ContainerInterface $container;

    public function testHandlesNormalMiddleware(): void
    {
        $middleware = $this->getMiddlewareDispatcher($this->container);
        $response = $middleware->handle(new ServerRequest('GET', '/hello'));
        self::assertArrayHasKey('Middleware', $response->getHeaders());
    }

    private function getMiddlewareDispatcher(ContainerInterface $container): RequestHandlerInterface
    {
        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        return new \Riaf\MiddlewareDispatcher($container);
    }

    public function testCallsMiddlewaresBasedOnPriority(): void
    {
        $middleware = $this->getMiddlewareDispatcher($this->container);
        $response = $middleware->handle(new ServerRequest('GET', '/hello'));
        self::assertArrayHasKey('Middleware', $response->getHeaders());
        self::assertEquals('Normal, Priority', $response->getHeaderLine('Middleware'));
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive([PriorityMiddleware::class], [NormalMiddleware::class], [ResponseFactoryInterface::class])
            ->willReturn(new PriorityMiddleware(), new NormalMiddleware(), new Psr17Factory());

        // Why? Well, setUpBeforeClasses is not counted for coverage..
        if (class_exists('\\Riaf\\MiddlewareDispatcher', false)) {
            return;
        }

        $config = new class() extends SampleCompilerConfiguration {
            public function getAdditionalServices(): array
            {
                return [
                    NormalMiddleware::class,
                    PriorityMiddleware::class,
                ];
            }
        };

        $compiler = new MiddlewareDispatcherCompiler($config);
        $compiler->supportsCompilation();
        $compiler->compile();

        $stream = fopen($config->getProjectRoot() . $config->getMiddlewareDispatcherFilepath(), 'rb');
        $content = stream_get_contents($stream);
        eval('?>' . $content);
    }
}
