<?php

declare(strict_types=1);

namespace Riaf\Compiler\Configuration;

interface ContainerCompilerConfiguration
{
    public function getContainerNamespace(): string;

    public function getContainerFilepath(): string;

    /**
     * Must return an array of key-value strings
     * Key: A class (can be abstract) or interface, or alias
     * Value: An actual class-string.
     *
     * If Key is unequal Value, then Value is treated as the implementation for Key
     * making both Key and Value available for injection.
     *
     * @return array<string, string>
     */
    public function getAdditionalClasses(): array;
}
