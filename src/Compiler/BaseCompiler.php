<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Metrics\Timing;

abstract class BaseCompiler
{
    public function __construct(protected AnalyzerInterface $analyzer, protected Timing $timing, protected BaseConfiguration $config)
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

    protected function getOutputFile(string $filePath, BaseCompiler $compiler): ?string
    {
        $handle = $this->config->getFileHandle($compiler);

        if ($handle === null) {
            return $this->config->getProjectRoot() . $filePath;
        }

        return null;
    }
}
