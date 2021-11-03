<?php

declare(strict_types=1);

namespace Riaf\TestCases\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\PsrExtensions\Middleware\Middleware;

#[Middleware(100)]
class PriorityMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withAddedHeader('Middleware', 'Priority');
    }
}
