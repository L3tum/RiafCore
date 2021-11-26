<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Middleware;

use Exception;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\PsrExtensions\Http\BadRequestException;
use Riaf\PsrExtensions\Http\HttpException;
use Throwable;

class ExceptionCatchingMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (BadRequestException $e) {
            return $this->responseFactory->createResponse(400);
        } catch (HttpException $e) {
            return $this->responseFactory->createResponse($e->getCode());
        } catch (Exception|Throwable) {
            return $this->responseFactory->createResponse(500);
        }
    }
}
