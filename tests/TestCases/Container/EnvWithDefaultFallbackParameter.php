<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class EnvWithDefaultFallbackParameter
{
    public function __construct(private string $value = 'Default')
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
