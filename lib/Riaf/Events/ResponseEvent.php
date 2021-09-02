<?php

namespace Riaf\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ResponseEvent extends CoreEvent
{
    public function __construct(protected ServerRequestInterface $request, protected ResponseInterface $response)
    {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): ResponseEvent
    {
        $this->request = $request;

        return $this;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function setResponse(ResponseInterface $response): ResponseEvent
    {
        $this->response = $response;

        return $this;
    }
}
