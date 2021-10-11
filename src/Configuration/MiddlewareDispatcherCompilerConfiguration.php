<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface MiddlewareDispatcherCompilerConfiguration
{
    public function getMiddlewareDispatcherNamespace(): string;

    public function getMiddlewareDispatcherFilepath(): string;
}
