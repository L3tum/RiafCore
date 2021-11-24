<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Generator;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Riaf\Compiler\Emitter\PreloadingEmitter;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\EventDispatcherCompilerConfiguration;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Configuration\PreloadCompilerConfiguration;
use Riaf\Configuration\RouterCompilerConfiguration;

class PreloadingCompiler extends BaseCompiler
{
    /**
     * @var array<string, bool>
     */
    private array $preloadedFiles = [];

    private ?PreloadingEmitter $emitter = null;

    public function supportsCompilation(): bool
    {
        return $this->config instanceof PreloadCompilerConfiguration;
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);
        $this->emitter = new PreloadingEmitter($this->config, $this, $this->logger);
        /** @var PreloadCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getPreloadingFilepath(), $this)]);

        foreach ($classes as $class) {
            $this->preloadClass($class);
        }

        foreach ($config->getAdditionalPreloadedFiles() as $additionalPreloadedFile) {
            if (!str_starts_with($additionalPreloadedFile, $this->config->getProjectRoot())) {
                $additionalPreloadedFile = $this->config->getProjectRoot() . '/' . trim($additionalPreloadedFile, '/');
            }

            $this->preloadedFiles[$additionalPreloadedFile] = true;
        }

        // Preload Results of other Compilers
        if ($this->config instanceof ContainerCompilerConfiguration) {
            $this->logger->debug('Preloading Generated Container');
            $file = $this->config->getProjectRoot() . $this->config->getContainerFilepath();

            if (file_exists($file)) {
                $this->preloadedFiles[$file] = true;
                $this->preloadedFiles[(new ReflectionClass(ContainerInterface::class))->getFileName() ?: ''] = true;
            }
        }

        if ($this->config instanceof EventDispatcherCompilerConfiguration) {
            $this->logger->debug('Preloading Generated EventDispatcher');
            $file = $this->config->getProjectRoot() . $this->config->getEventDispatcherFilepath();

            if (file_exists($file)) {
                $this->preloadedFiles[$file] = true;
                $this->preloadedFiles[(new ReflectionClass(EventDispatcherInterface::class))->getFileName() ?: ''] = true;
                $this->preloadedFiles[(new ReflectionClass(StoppableEventInterface::class))->getFileName() ?: ''] = true;
            }
        }

        if ($this->config instanceof MiddlewareDispatcherCompilerConfiguration) {
            $this->logger->debug('Preloading Generated MiddlewareDispatcher');
            $file = $this->config->getProjectRoot() . $this->config->getMiddlewareDispatcherFilepath();

            if (file_exists($file)) {
                $this->preloadedFiles[$file] = true;
                $this->preloadedFiles[(new ReflectionClass(MiddlewareInterface::class))->getFileName() ?: ''] = true;
                $this->preloadedFiles[(new ReflectionClass(RequestHandlerInterface::class))->getFileName() ?: ''] = true;
            }
        }

        if ($this->config instanceof RouterCompilerConfiguration) {
            $this->logger->debug('Preloading Generated Router');
            $file = $this->config->getProjectRoot() . $this->config->getRouterFilepath();

            if (file_exists($file)) {
                $this->preloadedFiles[$file] = true;
                $this->preloadedFiles[(new ReflectionClass(MiddlewareInterface::class))->getFileName() ?: ''] = true;
                $this->preloadedFiles[(new ReflectionClass(RequestHandlerInterface::class))->getFileName() ?: ''] = true;
            }
        }

        $this->emitter->emitPreloading($this->preloadedFiles);
        $this->preloadedFiles = [];

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function preloadClass(ReflectionClass $class): void
    {
        $preloadableClasses = $this->analyzeClassUsageInClass($class);

        foreach ($preloadableClasses as $preloadableClass) {
            /** @var ReflectionClass<object> $preloadableClass */
            $filePath = $preloadableClass->getFileName();

            if ($filePath === false) {
                $this->logger->debug("Cannot preload {$preloadableClass->getName()} because there is no file");
                continue;
            }
            $this->preloadedFiles[$filePath] = true;
        }
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
            $this->logger->debug("Cannot preload {$class->getName()} because it's not user-defined");

            return;
        }

        $filePath = $class->getFileName();

        if (empty($filePath)) {
            $this->logger->debug("Cannot preload {$class->getName()} because there is no file");

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

    /**
     * @param string[] $services
     */
    public function addAdditionalServices(array $services): void
    {
        foreach ($services as $service) {
            if (class_exists($service) || interface_exists($service) || trait_exists($service)) {
                $this->preloadClass(new ReflectionClass($service));
            } else {
                $this->logger->debug("Cannot preload $service as it does not exist");
            }
        }
    }
}
