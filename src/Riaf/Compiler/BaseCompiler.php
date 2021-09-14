<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Metrics\Timing;

abstract class BaseCompiler
{
    private string $lineBreak = PHP_EOL;

    public function __construct(protected AnalyzerInterface $analyzer, protected Timing $timing, protected CompilerConfiguration $config)
    {
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

    /**
     * @param resource $fileHandle
     */
    protected function writeLine(&$fileHandle, string $line = '', int $indentation = 0): void
    {
        /* @phpstan-ignore-next-line */
        fwrite($fileHandle, implode(array_fill(0, $indentation, "\t")) . $line . $this->lineBreak);
    }

    /**
     * @return resource
     *
     * @throws Exception
     */
    protected function openResultFile(string $path)
    {
        $handle = $this->config->getFileHandle($this);

        if ($handle === null) {
            $handle = fopen($this->config->getProjectRoot() . $path, 'wb+');
        }

        if ($handle === false) {
            // TODO: Exception
            throw new Exception();
        }

        return $handle;
    }
}
