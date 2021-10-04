<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class NamedConstantArrayParameter
{
    public const DEFAULT = [
        'Hello',
    ];

    public function __construct(private array $value = self::DEFAULT)
    {
    }

    public function getValue(): array
    {
        return $this->value;
    }
}
