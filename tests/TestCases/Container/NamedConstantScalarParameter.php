<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class NamedConstantScalarParameter
{
    public const DEFAULT = 'Hello';

    public function __construct(private string $value = self::DEFAULT)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
