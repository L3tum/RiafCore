<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class IndirectSelfDependencyOne
{
    public function __construct(private IndirectSelfDependencyTwo $dependencyTwo)
    {
    }
}
