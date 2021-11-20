<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class FactoryMethodNoParameters
{
    public function __construct(public string $creator = 'Constructor')
    {
    }

    public static function create(): self
    {
        return new self('Factory');
    }
}
