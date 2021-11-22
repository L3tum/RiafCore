<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Generator;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Emitter\PreloadingEmitter;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\PreloadCompilerConfiguration;
use Riaf\Metrics\Timing;

class PreloadingCompiler extends BaseCompiler
{
    /**
     * @var array<string, bool>
     */
    private array $preloadedFiles = [];

    private PreloadingEmitter $emitter;

    #[Pure]
    public function __construct(BaseConfiguration $config, ?AnalyzerInterface $analyzer = null, ?Timing $timing = null)
    {
        parent::__construct($config, $analyzer, $timing);
        $this->emitter = new PreloadingEmitter($config, $this);
    }

    public function supportsCompilation(): bool
    {
        return $this->config instanceof PreloadCompilerConfiguration;
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);
        /** @var PreloadCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getPreloadingFilepath(), $this)]);

        foreach ($classes as $class) {
            $preloadableClasses = $this->analyzeClassUsageInClass($class);

            foreach ($preloadableClasses as $preloadableClass) {
                /** @var ReflectionClass<object> $preloadableClass */
                $filePath = $preloadableClass->getFileName();

                if ($filePath === false) {
                    continue;
                }
                $this->preloadedFiles[$filePath] = true;
            }
        }

        foreach ($config->getAdditionalPreloadedFiles() as $additionalPreloadedFile) {
            if (!str_starts_with($additionalPreloadedFile, $this->config->getProjectRoot())) {
                $additionalPreloadedFile = $this->config->getProjectRoot() . '/' . $additionalPreloadedFile;
            }

            $this->preloadedFiles[$additionalPreloadedFile] = true;
        }

        $this->emitter->emitPreloading($this->preloadedFiles);
        $this->preloadedFiles = [];

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return Generator<ReflectionClass<object>>
     */
    private function analyzeClassUsageInClass(ReflectionClass $class): Generator
    {
        // Skip built-in classes
        if (!$class->isUserDefined()) {
            return;
        }

        $filePath = $class->getFileName();

        if (empty($filePath)) {
            return;
        }

        if (isset($this->preloadedFiles[$filePath])) {
            return;
        }

        $this->preloadedFiles[$filePath] = true;

        // Parent-Classes
        $parentClasses = $this->getParentClasses($class);
        foreach ($parentClasses as $parentClass) {
            yield from $this->analyzeClassUsageInClass($parentClass);
        }

        // Traits
        foreach ($class->getTraits() as $trait) {
            yield from $this->analyzeClassUsageInClass($trait);
        }

        // Properties
        foreach ($class->getProperties() as $property) {
            $propertyClass = $this->getReflectionClassFromReflectionType($property->getType());

            if ($propertyClass !== null) {
                yield from $this->analyzeClassUsageInClass($propertyClass);
            }
        }

        // Methods
        foreach ($class->getMethods() as $method) {
            $returnClass = $this->getReflectionClassFromReflectionType($method->getReturnType());

            if ($returnClass !== null) {
                yield from $this->analyzeClassUsageInClass($returnClass);
            }

            foreach ($method->getParameters() as $parameter) {
                $parameterClass = $this->getReflectionClassFromReflectionType($parameter->getType());

                if ($parameterClass !== null) {
                    yield from $this->analyzeClassUsageInClass($parameterClass);
                }
            }
        }

        yield $class;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return Generator<ReflectionClass<object>>
     */
    private function getParentClasses(ReflectionClass $class): Generator
    {
        $parentClass = $class->getParentClass();
        while ($parentClass !== false) {
            yield $parentClass;

            $parentClass = $parentClass->getParentClass();
        }
    }
}
