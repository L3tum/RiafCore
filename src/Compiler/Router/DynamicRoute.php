<?php

declare(strict_types=1);

namespace Riaf\Compiler\Router;

class DynamicRoute
{
    public int $index = -1;

    public ?string $pattern = null;

    public ?string $parameter = null;

    public ?string $targetClass = null;

    public ?string $targetMethod = null;

    public bool $isStaticTarget = false;

    public bool $captureParameter = false;

    /** @var array<string, DynamicRoute> */
    public array $next = [];

    public function setIndex(int $index): DynamicRoute
    {
        $this->index = $index;

        return $this;
    }

    public function setPattern(?string $pattern): DynamicRoute
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function setParameter(string $parameter): DynamicRoute
    {
        $this->parameter = $parameter;

        return $this;
    }

    public function setTargetClass(string $targetClass): DynamicRoute
    {
        $this->targetClass = $targetClass;

        return $this;
    }

    public function setTargetMethod(string $targetMethod): DynamicRoute
    {
        $this->targetMethod = $targetMethod;

        return $this;
    }

    /**
     * @param array<string, DynamicRoute> $next
     */
    public function setNext(array $next): DynamicRoute
    {
        $this->next = $next;

        return $this;
    }

    public function setIsStaticTarget(bool $isStaticTarget): DynamicRoute
    {
        $this->isStaticTarget = $isStaticTarget;

        return $this;
    }

    public function setCaptureParameter(bool $captureParameter): DynamicRoute
    {
        $this->captureParameter = $captureParameter;

        return $this;
    }

    public function addNext(string $key, DynamicRoute $next): self
    {
        $this->next[$key] = $next;

        return $this;
    }

    public function hasTarget(): bool
    {
        return $this->targetClass !== null && $this->targetMethod !== null;
    }

    public function hasNext(): bool
    {
        return count($this->next) > 0;
    }
}
