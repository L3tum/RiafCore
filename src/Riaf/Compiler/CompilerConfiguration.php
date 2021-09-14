<?php

declare(strict_types=1);

namespace Riaf\Compiler;

abstract class CompilerConfiguration
{
    public function getProjectRoot(): string
    {
        return getcwd() ?: '';
    }

    /**
     * @return resource|null
     */
    public function getFileHandle(BaseCompiler $compiler)
    {
        return null;
    }

    /**
     * @return string[]
     */
    public function getAdditionalCompilers(): array
    {
        return [];
    }
}
