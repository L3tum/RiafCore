<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class EnvWithFallbackParameter
{
    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
