<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class DefaultBoolParameter
{
    public function __construct(private bool $value = true)
    {
    }

    public function isValue(): bool
    {
        return $this->value;
    }
}
