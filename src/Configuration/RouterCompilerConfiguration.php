<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface RouterCompilerConfiguration
{
    public function getRouterNamespace(): string;

    public function getRouterFilepath(): string;
}
