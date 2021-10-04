<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class InjectedFloatParameter
{
    public function __construct(private float $injectedFloat = 0.0)
    {
    }

    public function getInjectedFloat(): float
    {
        return $this->injectedFloat;
    }
}
