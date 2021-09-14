<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ArrayIterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;
use Riaf\PsrExtensions\Middleware\Middleware;
use Riaf\Routing\Route;

/**
 * @runTestsInSeparateProcesses
 */
class RouterCompilerTest extends TestCase
{
    private MockObject|AnalyzerInterface $analyzer;

    private $mockingClass;

    private SampleCompilerConfiguration $config;

    private RouterCompiler $compiler;

    /**
     * @var MockObject|RequestHandlerInterface
     */
    private MockObject|RequestHandlerInterface $requestHandler;

    public function testImplementsRequestHandlerInterface(): void
    {
        $this->compiler->compile();
        $router = $this->getRouter($this->createMock(ContainerInterface::class));
        self::assertInstanceOf(MiddlewareInterface::class, $router);
        self::assertInstanceOf(RequestHandlerInterface::class, $router);
        self::assertTrue(method_exists($router, 'process'));
        self::assertTrue(method_exists($router, 'handle'));
        self::assertNotEmpty((new ReflectionClass($router))->getAttributes(Middleware::class));
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private function getRouter(ContainerInterface $container): MiddlewareInterface
    {
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        eval('?>' . $content);

        return new \Riaf\Router($container);
    }

    public function testCallCorrectShallowHandler(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('Hey', (string) $response->getBody());
    }

    public function testCallCorrectDeepHandler(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/this/is/deep');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('Bye', (string) $response->getBody());
    }

    public function testInjectsRequestIfRequested(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/get/request');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('/get/request', (string) $response->getBody());
    }

    public function testInjectsParameterWithoutRequirement(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/5');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('5', (string) $response->getBody());
    }

    public function testReturns404IfUrlIsTooLongWithParameter(): void
    {
        $this->compiler->compile();

        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/5/this/is/no/match');
        /** @var ResponseInterface $response */
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    public function testInjectsParameterWithRequirement(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/requirement/5');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('5', (string) $response->getBody());
    }

    public function testReturns404IfRequirementDoesNotMatch(): void
    {
        $this->compiler->compile();

        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/requirement/hello');
        /** @var ResponseInterface $response */
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    public function testInjectsParameterAndRequest(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/request/5');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('/param/request/55', (string) $response->getBody());
    }

    public function testInjectsMultipleParameterWithoutRequirements(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/4/2');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('42', (string) $response->getBody());
    }

    public function testInjectsMultipleParameterWithRequirements(): void
    {
        $this->compiler->compile();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($this->mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/requirements/4/2');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals('42', (string) $response->getBody());
    }

    public function testReturns404IfOneOfRequirementsDoesNotMatch(): void
    {
        $this->compiler->compile();

        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/requirements/hello/2');
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals(404, $response->getStatusCode());

        $request = new ServerRequest('GET', '/param/multiple/requirements/4/hello');
        $response = $router->process($request, $this->requestHandler);
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testReturns404OnNonMatch(): void
    {
        $this->compiler->compile();

        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/thisisnomatchforyou');
        /** @var ResponseInterface $response */
        $response = $router->process($request, $this->requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }
        };

        $this->analyzer = $this->createMock(AnalyzerInterface::class);
        $this->mockingClass = new class() {
            #[Route('/')]
            public function handle()
            {
                return new Response(body: 'Hey');
            }

            #[Route('/this/is/deep')]
            public function handleDeep()
            {
                return new Response(body: 'Bye');
            }

            #[Route('/get/request')]
            public function handleRequest(ServerRequestInterface $request)
            {
                return new Response(body: $request->getUri()->getPath());
            }

            #[Route('/param/{id}')]
            public function handleParameterWithoutRequirement(int $id)
            {
                return new Response(body: (string) $id);
            }

            #[Route('/param/requirement/{id}', requirements: ['id' => '\d'])]
            public function handleParameterWithRequirement(int $id)
            {
                return new Response(body: (string) $id);
            }

            #[Route('/param/request/{id}')]
            public function handleParameterAndRequestInjection(int $id, ServerRequestInterface $request)
            {
                return new Response(body: $request->getUri()->getPath() . (string) $id);
            }

            #[Route('/param/multiple/{id}/{anotherId}')]
            public function handleMultipleParameterWithoutRequirements(int $id, int $anotherId)
            {
                return new Response(body: $id . $anotherId);
            }

            #[Route('/param/multiple/requirements/{id}/{anotherId}', requirements: ['id' => '\d', 'anotherId' => '\d'])]
            public function handleMultipleParameterWithRequirements(int $id, int $anotherId)
            {
                return new Response(body: $id . $anotherId);
            }
        };
        $this->analyzer->expects($this->once())->method('getUsedClasses')->with($this->config->getProjectRoot())->willReturn(new ArrayIterator([new ReflectionClass($this->mockingClass)]));

        $this->compiler = new RouterCompiler($this->analyzer, new Timing(new SystemClock()), $this->config);

        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
    }
}
