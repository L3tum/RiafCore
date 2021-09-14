<?php

declare(strict_types=1);

namespace Riaf\Events;

use Psr\Http\Message\ServerRequestInterface;

class RequestEvent extends CoreEvent
{
    public function __construct(protected ServerRequestInterface $request)
    {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): RequestEvent
    {
        $this->request = $request;

        return $this;
    }
}
