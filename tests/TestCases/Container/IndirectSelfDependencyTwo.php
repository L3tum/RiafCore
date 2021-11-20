<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class IndirectSelfDependencyTwo
{
    public function __construct(private IndirectSelfDependencyOne $dependencyOne)
    {
    }
}
