<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class DefaultIntegerTestCase
{
    public function __construct(private int $value = 0)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
