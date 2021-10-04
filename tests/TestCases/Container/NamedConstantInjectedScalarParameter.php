<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class NamedConstantInjectedScalarParameter
{
    public const DEFAULT = 'Hello';

    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
