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
        $basePath = $config->getPreloadingBasePath();
        $basePath = $basePath !== null ? rtrim($basePath, '/') : $basePath;
        $root = $this->config->getProjectRoot();

        foreach ($files as $preloadableFile) {
            if ($basePath !== null) {
                $preloadableFile = str_replace($root, $basePath, $preloadableFile);
            }

            if (!empty($preloadableFile)) {
                $this->writeLine("opcache_compile_file(\"$preloadableFile\");");
            }
        }
    }
}
