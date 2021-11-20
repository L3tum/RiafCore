<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class FactoryMethodWithParameters
{
    public function __construct(public string $creator = 'Constructor')
    {
    }

    public static function create(DefaultBoolParameter $parameter, DefaultFloatParameter $floatParameter): self
    {
        return new self('Factory');
    }
}
