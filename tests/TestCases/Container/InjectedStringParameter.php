<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class InjectedStringParameter
{
    public function __construct(private string $injectedName = 'wrong')
    {
    }

    public function getInjectedName(): string
    {
        return $this->injectedName;
    }
}
