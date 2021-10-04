<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class DefaultStringParameterTestCase
{
    public function __construct(private string $name = 'Hello')
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
