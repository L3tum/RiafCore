<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface RouterCompilerConfiguration
{
    public function getRouterNamespace(): string;

    public function getRouterFilepath(): string;

    /**
     * Must return an array of class-strings.
     * E.g. [MyRouter::class].
     *
     * @return string[]
     */
    public function getAdditionalRouterClasses(): array;
}
