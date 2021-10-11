<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Metrics\Timing;
use RuntimeException;

abstract class BaseCompiler
{
    protected ?string $outputFile = null;

    private string $lineBreak = PHP_EOL;

    /**
     * @var resource|null
     */
    private $backingHandle = null;

    /**
     * @var resource|null
     */
    private $handle = null;

    public function __construct(protected AnalyzerInterface $analyzer, protected Timing $timing, protected BaseConfiguration $config)
    {
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            if ($this->backingHandle !== null) {
                rewind($this->handle);
                rewind($this->backingHandle);
                stream_copy_to_stream($this->handle, $this->backingHandle);
            }

            fclose($this->handle);
        }

        if ($this->backingHandle !== null) {
            fclose($this->backingHandle);
        }
    }

    abstract public function compile(): bool;

    abstract public function supportsCompilation(): bool;

    /**
     * @return ReflectionClass<object>|null
     */
    protected function getReflectionClassFromReflectionType(?ReflectionType $reflectionType): ?ReflectionClass
    {
        if ($reflectionType === null) {
            return null;
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            $typeName = $reflectionType->getName();

            if (class_exists($typeName) || interface_exists($typeName)) {
                try {
                    return new ReflectionClass($typeName);
                } catch (ReflectionException) {
                    // Intentionally left blank
                }
            }
        }

        return null;
    }

    protected function writeLine(string $line = '', int $indentation = 0): void
    {
        if ($this->handle === null) {
            // TODO: Exception
            throw new RuntimeException();
        }

        $indentation = min($indentation, 10);
        /* @phpstan-ignore-next-line */
        fwrite($this->handle, implode(array_fill(0, $indentation, "\t")) . $line . $this->lineBreak);
    }

    /**
     * @return resource
     *
     * @throws Exception
     */
    protected function openResultFile(string $path)
    {
        $this->handle = $this->config->getFileHandle($this);

        if ($this->handle === null) {
            $this->outputFile = $this->config->getProjectRoot() . $path;
            $this->backingHandle = fopen($this->outputFile, 'wb+') ?: null;
            $this->handle = fopen('php://memory', 'wb+');
        }

        if ($this->handle === null) {
            // TODO: Exception
            throw new Exception();
        }

        return $this->handle;
    }
}
