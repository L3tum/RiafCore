<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class DefaultFloatParameter
{
    public function __construct(private float $value = 0.0)
    {
    }

    public function getValue(): float
    {
        return $this->value;
    }
}
