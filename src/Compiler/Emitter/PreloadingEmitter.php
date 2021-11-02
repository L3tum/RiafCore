<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use JetBrains\PhpStorm\Pure;
use Riaf\Compiler\PreloadingCompiler;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\PreloadCompilerConfiguration;

class PreloadingEmitter extends BaseEmitter
{
    #[Pure]
    public function __construct(BaseConfiguration $config, PreloadingCompiler $compiler)
    {
        parent::__construct($config, $compiler);
    }

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

        foreach ($preloadableFiles as $preloadableFile => $_) {
            $this->writeLine("opcache_compile_file(\"$preloadableFile\");");
        }
    }
}
