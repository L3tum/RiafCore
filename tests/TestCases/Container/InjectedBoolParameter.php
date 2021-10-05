<?php

namespace Riaf\TestCases\Container;

class InjectedBoolParameter
{
    public function __construct(private bool $value = false)
    {
    }

    public function isValue(): bool
    {
        return $this->value;
    }
}
