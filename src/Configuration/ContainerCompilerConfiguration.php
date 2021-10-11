<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface ContainerCompilerConfiguration
{
    public function getContainerNamespace(): string;

    public function getContainerFilepath(): string;
}
