<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class InjectedIntegerParameter
{
    public function __construct(private int $injectedValue = 0)
    {
    }

    public function getInjectedValue(): int
    {
        return $this->injectedValue;
    }
}
