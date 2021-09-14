<?php

declare(strict_types=1);

namespace Riaf\Compiler\Configuration;

interface RouterCompilerConfiguration
{
    public function getRouterNamespace(): string;

    public function getRouterFilepath(): string;
}
