<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

class InjectedServiceSkipParameter
{
    public function __construct(private object $compiler)
    {
    }

    public function getCompiler(): object
    {
        return $this->compiler;
    }
}
