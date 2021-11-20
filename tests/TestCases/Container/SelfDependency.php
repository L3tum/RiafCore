<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class SelfDependency
{
    public function __construct(private SelfDependency $selfDependency, private SelfDependency $selfDependency2)
    {
    }
}
