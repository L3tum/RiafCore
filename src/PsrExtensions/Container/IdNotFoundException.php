<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Container;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class IdNotFoundException extends Exception implements NotFoundExceptionInterface
{
}
