<?php

namespace Riaf\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Riaf\Core;

class TerminateEvent extends CoreEvent
{
    public function __construct(protected Core $core, protected ServerRequestInterface $request, protected ResponseInterface $response)
    {
    }

    public function getCore(): Core
    {
        return $this->core;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
