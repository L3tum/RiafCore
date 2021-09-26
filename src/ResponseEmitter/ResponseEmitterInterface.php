<?php

declare(strict_types=1);

namespace Riaf\ResponseEmitter;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    public function emitResponse(ResponseInterface $response): void;
}
