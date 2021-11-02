<?php

declare(strict_types=1);

namespace Riaf\Compiler\Router;

class StaticRoute
{
    public function __construct(protected string $uri, protected string $method, protected string $targetClass, protected string $targetMethod, protected bool $static)
    {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getTargetClass(): string
    {
        return $this->targetClass;
    }

    public function getTargetMethod(): string
    {
        return $this->targetMethod;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }
}
