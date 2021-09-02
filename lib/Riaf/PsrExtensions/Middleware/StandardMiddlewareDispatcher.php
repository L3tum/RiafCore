<?php

namespace Riaf\PsrExtensions\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class StandardMiddlewareDispatcher implements MiddlewareDispatcherInterface
{
    /**
     * @var array<int, MiddlewareInterface>
     */
    protected array $middlewares = [];
    protected int $currentMiddleware = -1;

    public function __construct(protected ResponseInterface $response)
    {
    }

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->currentMiddleware;

        if (!isset($this->middlewares[$this->currentMiddleware])) {
            return $this->response;
        }

        $this->response = $this->middlewares[$this->currentMiddleware]->process($request, $this);

        return $this->response;
    }
}
