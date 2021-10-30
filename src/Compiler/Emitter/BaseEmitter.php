<?php

namespace Riaf\Compiler\Emitter;

use Exception;
use Riaf\Compiler\BaseCompiler;
use Riaf\Configuration\BaseConfiguration;
use RuntimeException;

class BaseEmitter
{
    protected ?string $outputFile = null;

    private string $lineBreak = PHP_EOL;

    /**
     * @var resource|null
     */
    private $handle = null;

    public function __construct(protected BaseConfiguration $config, protected BaseCompiler $compiler)
    {
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            fclose($this->handle);
        }
    }

    protected function writeLine(string $line = '', int $indentation = 0): void
    {
        if ($this->handle === null) {
            // TODO: Exception
            throw new RuntimeException();
        }

        $indentation = min($indentation, 10);
        /** @phpstan-ignore-next-line */
        $indents = implode(array_fill(0, $indentation, "\t"));
        fwrite($this->handle, "$indents$line{$this->lineBreak}");
    }

    /**
     * @return resource
     *
     * @throws Exception
     */
    protected function openResultFile(string $path)
    {
        $this->handle = $this->config->getFileHandle($this->compiler);

        if ($this->handle === null) {
            $this->outputFile = $this->config->getProjectRoot() . $path;
            $this->handle = fopen($this->outputFile, 'wb+') ?: null;
        }

        if ($this->handle === null) {
            // TODO: Exception
            throw new Exception();
        }

        return $this->handle;
    }
}
