<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use Riaf\Configuration\PreloadCompilerConfiguration;

class PreloadingEmitter extends BaseEmitter
{
    /**
     * @param array<string, bool> $preloadableFiles
     *
     * @throws Exception
     */
    public function emitPreloading(array &$preloadableFiles): void
    {
        /** @var PreloadCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getPreloadingFilepath());
        $this->writeLine('<?php');
        $files = array_keys($preloadableFiles);

        foreach ($files as $preloadableFile) {
            $this->writeLine("opcache_compile_file(\"$preloadableFile\");");
        }
    }
}
