<?php

namespace Riaf\ResponseEmitter;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    public function emitResponse(ResponseInterface $response): void;
}
