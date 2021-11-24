<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface PreloadCompilerConfiguration
{
    public function getPreloadingFilepath(): string;

    /** @return string[] */
    public function getAdditionalPreloadedFiles(): array;

    /**
     * Replaces the project root with a set base path. Useful if your deployment target is different from where
     * you generate the preloading file.
     * Return NULL to keep the project root.
     */
    public function getPreloadingBasePath(): ?string;
}
