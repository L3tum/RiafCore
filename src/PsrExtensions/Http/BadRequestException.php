<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Http;

use JetBrains\PhpStorm\Pure;
use Throwable;

class BadRequestException extends HttpException
{
    #[Pure]
    public function __construct(string $message = '', int $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
