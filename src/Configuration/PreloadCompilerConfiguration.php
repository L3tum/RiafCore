<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface PreloadCompilerConfiguration
{
    public function getPreloadingFilepath(): string;

    /** @return string[] */
    public function getAdditionalPreloadedFiles(): array;
}
