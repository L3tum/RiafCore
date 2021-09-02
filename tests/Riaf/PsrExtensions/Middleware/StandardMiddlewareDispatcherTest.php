<?php

namespace Riaf\PsrExtensions\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StandardMiddlewareDispatcherTest extends TestCase
{
    private StandardMiddlewareDispatcher $dispatcher;
    private Response $response;

    public function testImplementsRequestHandlerInterface(): void
    {
        self::assertInstanceOf(MiddlewareDispatcherInterface::class, $this->dispatcher);
        self::assertInstanceOf(RequestHandlerInterface::class, $this->dispatcher);
    }

    public function testReturnsDefaultResponseWithoutMiddlewares(): void
    {
        $request = new ServerRequest('GET', '/');
        $response = $this->dispatcher->handle($request);
        self::assertEquals((string) $this->response->getBody(), (string) $response->getBody());
    }

    public function testCallsMiddleware(): void
    {
        $request = new ServerRequest('GET', '/');
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->with($request, $this->dispatcher)->willReturn(new Response(body: 'Hello'));
        $this->dispatcher->addMiddleware($middleware);
        $response = $this->dispatcher->handle($request);
        self::assertEquals('Hello', (string) $response->getBody());
    }

    protected function setUp(): void
    {
        $this->response = new Response(200, [], 'Default Response');
        $this->dispatcher = new StandardMiddlewareDispatcher($this->response);
    }
}
