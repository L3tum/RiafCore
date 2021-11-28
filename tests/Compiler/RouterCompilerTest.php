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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\PsrExtensions\Middleware\Middleware;
use Riaf\Routing\Route;
use Riaf\TestCases\Router\StaticFunction;

class RouterCompilerTest extends TestCase
{
    private static $mockingClass;

    private static MockObject|RequestHandlerInterface $requestHandler;

    public function testImplementsRequestHandlerInterface(): void
    {
        $router = $this->getRouter($this->createMock(ContainerInterface::class));
        self::assertInstanceOf(MiddlewareInterface::class, $router);
        self::assertInstanceOf(RequestHandlerInterface::class, $router);
        self::assertTrue(method_exists($router, 'process'));
        self::assertTrue(method_exists($router, 'handle'));
        self::assertNotEmpty((new ReflectionClass($router))->getAttributes(Middleware::class));
    }

    private function getRouter(ContainerInterface $container): MiddlewareInterface
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        return new \Riaf\Router($container);
    }

    public function testCallCorrectShallowHandler(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('Hey', (string) $response->getBody());
    }

    public function testCallCorrectDeepHandler(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/this/is/deep');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('Bye', (string) $response->getBody());
    }

    public function testInjectsRequestIfRequested(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/get/request');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('/get/request', (string) $response->getBody());
    }

    public function testInjectsParameterWithoutRequirement(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/5');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('5', (string) $response->getBody());
    }

    public function testReturns404IfUrlIsTooLongWithParameter(): void
    {
        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/5/this/is/no/match');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    public function testInjectsParameterWithRequirement(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/requirement/5');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('5', (string) $response->getBody());
    }

    public function testReturns404IfRequirementDoesNotMatch(): void
    {
        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/requirement/hello');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    public function testInjectsParameterAndRequest(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/request/5');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('/param/request/55', (string) $response->getBody());
    }

    public function testInjectsMultipleParameterWithoutRequirements(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/4/2');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('42', (string) $response->getBody());
    }

    public function testInjectsMultipleParameterWithRequirements(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn(self::$mockingClass);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/requirements/4/2');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals('42', (string) $response->getBody());
    }

    public function testReturns404IfOneOfRequirementsDoesNotMatch(): void
    {
        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/param/multiple/requirements/hello/2');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals(404, $response->getStatusCode());

        $request = new ServerRequest('GET', '/param/multiple/requirements/4/hello');
        $response = $router->process($request, self::$requestHandler);
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testReturns404OnNonMatch(): void
    {
        $responseFactory = new Psr17Factory();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('get')->willReturn($responseFactory);

        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/thisisnomatchforyou');
        $response = $router->process($request, self::$requestHandler);

        self::assertEquals(404, $response->getStatusCode());
    }

    public function testMatchesCorrectManuallyAddedRoute(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->getRouter($container);
        $match = $router->match('GET', '/manually/added/route');

        self::assertEquals('manually::added', $match);
    }

    public function testMatchesCorrectManuallyAddedRouteWithHead(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->getRouter($container);
        $match = $router->match('HEAD', '/manually/added/route');

        self::assertEquals('manually::added', $match);
    }

    public function testHandlesStaticFunction(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->getRouter($container);
        $request = new ServerRequest('GET', '/static/function');
        $response = $router->process($request, self::$requestHandler);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function setUp(): void
    {
        // Why? Well, setUpBeforeClasses is not counted for coverage..
        if (class_exists('\\Riaf\\Router', false)) {
            return;
        }

        $analyzer = $this->createMock(AnalyzerInterface::class);
        $mockingClass = new class() {
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

        self::$mockingClass = $mockingClass;
        self::$requestHandler = $this->createMock(RequestHandlerInterface::class);

        $config = new SampleCompilerConfiguration();
        $analyzer->expects(self::once())->method('getUsedClasses')->with($config->getProjectRoot())->willReturn(new ArrayIterator([new ReflectionClass($mockingClass), new ReflectionClass(StaticFunction::class)]));

        $compiler = new RouterCompiler($config, $analyzer);
        $compiler->addRoute(new Route('/manually/added/route'), 'manually::added');
        $compiler->supportsCompilation();
        $compiler->compile();
        $stream = fopen($config->getProjectRoot() . $config->getRouterFilepath(), 'rb');
        $content = stream_get_contents($stream);
//        file_put_contents(dirname(__DIR__) . '/dev_Router.php', $content);
        eval('?>' . $content);
    }
}
