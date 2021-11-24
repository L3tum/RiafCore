<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Attribute;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Riaf\Compiler\Emitter\ContainerEmitter;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;
use Riaf\ResponseEmitter\ResponseEmitterInterface;
use Riaf\ResponseEmitter\StandardResponseEmitter;
use RuntimeException;
use Throwable;

class ContainerCompiler extends BaseCompiler
{
    /** @var string[] */
    private array $constructionMethodCache = [];

    /** @var array<string, ServiceDefinition|false> */
    private array $services = [];

    /** @var array<string, true> */
    private array $manuallyAddedServices = [];

    private ?ContainerEmitter $emitter = null;

    public function supportsCompilation(): bool
    {
        return $this->config instanceof ContainerCompilerConfiguration;
    }

    /**
     * @throws Exception
     */
    public function compile(): bool
    {
        $this->timing->start(self::class);
        $this->emitter = new ContainerEmitter($this->config, $this, $this->logger);
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;

        // First do a first look-over for ServiceDefinitions
        foreach ($this->config->getAdditionalServices() as $key => $value) {
            if ($value instanceof ServiceDefinition) {
                $this->analyzeServiceDefinition($key, $value);
            }
        }

        // Then go for all string-definitions and middleware definitions
        foreach ($this->config->getAdditionalServices() as $key => $value) {
            if (is_string($value)) {
                if (!class_exists($value)) {
                    throw new RuntimeException("Class $value does not exist!");
                }
                if (isset($this->services[$key])) {
                    unset($this->services[$key]);
                }
                $this->analyzeClass($key, new ReflectionClass($value));

                if (isset($this->services[$key])) {
                    $this->manuallyAddedServices[$key] = true;

                    // Add the classname as a key for the service
                    if ($key !== $value) {
                        if (isset($this->services[$value]) && !isset($this->manuallyAddedServices[$value])) {
                            $this->services[$value] = false;
                        } else {
                            $this->services[$value] = $this->services[$key];
                        }
                    }
                }
            } elseif ($value instanceof MiddlewareDefinition) {
                try {
                    if (isset($this->services[$key])) {
                        unset($this->services[$key]);
                    }
                    $this->analyzeClass($key, $value->getReflectionClass());

                    if (isset($this->services[$key])) {
                        $this->manuallyAddedServices[$key] = true;
                        $className = $value->getReflectionClass()->getName();

                        // Add the classname as a key for the service
                        if ($key !== $className) {
                            if (isset($this->services[$className]) && !isset($this->manuallyAddedServices[$className])) {
                                $this->services[$className] = false;
                            } else {
                                $this->services[$className] = $this->services[$key];
                            }
                        }
                    }
                } catch (Throwable) {
                    throw new RuntimeException('Class does not exist!');
                }
            }
        }

        // Lastly collect all project-related classes
        foreach ($this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getContainerFilepath(), $this)]) as $class) {
            /* @var ReflectionClass $class */
            if (!isset($this->services[$class->name])) {
                $this->analyzeClass($class->name, $class);
            }
        }

        // Add itself to Container
        $ownClass = $config->getContainerNamespace() . '\\Container';
        if (!isset($this->services[$ownClass]) && !isset($this->constructionMethodCache[$ownClass])) {
            $this->services[$ownClass] = new ServiceDefinition($ownClass);
            if (!isset($this->services[ContainerInterface::class])) {
                $this->services[ContainerInterface::class] = $this->services[$ownClass];
            }
            $this->constructionMethodCache[$ownClass] = '$this';
        } else {
            $this->logger->debug('Container is already defined, cannot add itself');
        }

        // Add current Config to Container
        $configClass = (new ReflectionClass($config))->getName();
        if (
            !(new ReflectionClass($config))->isAnonymous()
            && !isset($this->services[$configClass])
        ) {
            $this->services[$configClass] = new ServiceDefinition($configClass);
            if (!isset($this->services[BaseConfiguration::class])) {
                $this->services[BaseConfiguration::class] = $this->services[$configClass];
            }
            $this->constructionMethodCache[$configClass] = "new \\$configClass()";
        } else {
            $this->logger->debug('Cannot add Config to Container');
        }

        // Add Psr17Factory if possible
        if (
            class_exists("Nyholm\Psr7\Factory\Psr17Factory")
            && !isset($this->services["Psr\Http\Message\ServerRequestFactoryInterface"])
            && !isset($this->services["Psr\Http\Message\RequestFactoryInterface"])
            && !isset($this->services["Psr\Http\Message\UriFactoryInterface"])
            && !isset($this->services["Psr\Http\Message\ResponseFactoryInterface"])
            && !isset($this->services["Psr\Http\Message\StreamFactoryInterface"])
            && !isset($this->services["Psr\Http\Message\UploadedFileFactoryInterface"])
        ) {
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            $this->analyzeClass(\Nyholm\Psr7\Factory\Psr17Factory::class, new ReflectionClass(\Nyholm\Psr7\Factory\Psr17Factory::class));
        } else {
            $this->logger->debug('Psr17Factory is not defined');
        }

        // Add ServerRequestCreator if possible
        if (
            class_exists("Nyholm\Psr7Server\ServerRequestCreator")
            && !isset($this->services["Nyholm\Psr7Server\ServerRequestCreator"])
            && isset($this->services["Psr\Http\Message\ServerRequestFactoryInterface"])
            && isset($this->services["Psr\Http\Message\RequestFactoryInterface"])
            && isset($this->services["Psr\Http\Message\UriFactoryInterface"])
            && isset($this->services["Psr\Http\Message\ResponseFactoryInterface"])
            && isset($this->services["Psr\Http\Message\StreamFactoryInterface"])
            && isset($this->services["Psr\Http\Message\UploadedFileFactoryInterface"])
        ) {
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            $this->analyzeClass(\Nyholm\Psr7Server\ServerRequestCreator::class, new ReflectionClass(\Nyholm\Psr7Server\ServerRequestCreator::class));
        } else {
            $this->logger->debug('Cannot add ServerRequestCreator to Container');
        }

        // Add StandardResponseEmitter
        if (!isset($this->services[ResponseEmitterInterface::class])) {
            $this->analyzeClass(StandardResponseEmitter::class, new ReflectionClass(StandardResponseEmitter::class));
        } else {
            $this->logger->debug('Response Emitter already defined');
        }

        // Add some constants to Container
        if (!isset($this->services['coreDebug'])) {
            $this->services['coreDebug'] = new ServiceDefinition('coreDebug');
            $this->constructionMethodCache['coreDebug'] = '$this->get(\Riaf\Configuration\BaseConfiguration::class)->isDevelopmentMode()';
        }
        if (!isset($this->services['projectRoot'])) {
            $this->services['projectRoot'] = new ServiceDefinition('projectRoot');
            $this->constructionMethodCache['projectRoot'] = '$this->get(\Riaf\Configuration\BaseConfiguration::class)->getProjectRoot()';
        }

        $preloadingCompiler = new PreloadingCompiler($this->config, $this->analyzer, $this->timing, $this->logger);
        $this->emitter->emitContainer($this->services, $this->constructionMethodCache, $this->manuallyAddedServices, $preloadingCompiler);
        $this->services = [];
        $this->constructionMethodCache = [];
        $this->manuallyAddedServices = [];

        $this->timing->stop(self::class);

        return true;
    }

    private function analyzeServiceDefinition(string $key, ServiceDefinition $value): void
    {
        $this->manuallyAddedServices[$key] = true;
        $class = $value->getReflectionClass();

        // If the class does not exist, don't analyze it. Just pump it into the Container
        if ($class === null) {
            $this->services[$key] = $value;
        } else {
            if (isset($this->services[$key])) {
                unset($this->services[$key]);
            }

            $this->analyzeClass($key, $class, $value);

            // If something went wrong during Analysis save the ServiceDefinition anyways
            if (!isset($this->services[$key])) {
                $this->services[$key] = $value;
            }
        }

        $className = $value->getClassName();

        // Add the classname as a key for the service
        if ($key !== $className) {
            if (isset($this->services[$className]) && !isset($this->manuallyAddedServices[$className])) {
                $this->services[$className] = false;
            } else {
                $this->services[$className] = $this->services[$key];
            }
        }

        foreach ($value->getAliases() as $alias) {
            $this->services[$alias] = $this->services[$key];
        }

        // Go over the defined parameters
        // And for each parameter:
        //      1. Check if it has been recorded as a service
        //      2. Add the parameter to the list for separate methods
        //      3. Check the fallback
        /**
         * @psalm-suppress PossiblyFalseReference Explicitly set above...
         * @phpstan-ignore-next-line Explicitly set above, why is this so hard you stupid static analyzers
         */
        $parameters = $this->services[$key]->getParameters();
        foreach ($parameters as $parameter) {
            while ($parameter !== null) {
                if ($parameter->isInjected() && !isset($this->services[$parameter->getValue()])) {
                    if (class_exists($parameter->getValue())) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $this->analyzeClass($parameter->getValue(), new ReflectionClass($parameter->getValue()));
                    }
                }

                $parameter = $parameter->getFallback();
            }
        }
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function analyzeClass(string $key, ReflectionClass $class, ?ServiceDefinition $predefinedDefinition = null): void
    {
        // Skip Abstract Classes, non-instantiable, anonymous, attributes, exceptions and those we already analyzed
        if (
            isset($this->services[$key])
            || $class->isAbstract()
            || $class->isInterface()
            || !$class->isInstantiable()
            || $class->isAnonymous()
            || count($class->getAttributes(Attribute::class)) > 0
            || $class->implementsInterface(Throwable::class)
        ) {
            return;
        }

        $className = $class->getName();
        $definition = new ServiceDefinition($className);
        $this->services[$key] = $definition;

        // Record class as implementation for interface
        foreach ($class->getInterfaces() as $interface) {
            $interfaceName = $interface->getName();

            // Make sure we don't just repeat the current analysis
            if ($key !== $interfaceName && $className !== $interfaceName) {
                if (!isset($this->services[$interfaceName])) {
                    $this->services[$interfaceName] = $this->services[$key];
                } else {
                    $this->services[$interfaceName] = false;
                }
            }
        }

        // Walk the parent-tree upwards to analyze those
        $extensionClass = $class->getParentClass();
        while ($extensionClass !== null && $extensionClass !== false) {
            $extensionClassName = $extensionClass->getName();

            // Make sure we don't just repeat the current analysis
            if ($key !== $extensionClassName && $className !== $extensionClassName) {
                $this->analyzeClass($extensionClassName, $extensionClass);

                if ($extensionClass->isInterface() || $extensionClass->isAbstract()) {
                    if (!isset($this->services[$extensionClassName])) {
                        $this->services[$extensionClassName] = $this->services[$key];
                    } else {
                        $this->services[$extensionClassName] = false;
                    }
                }
            }

            $extensionClass = $extensionClass->getParentClass();
        }

        $constructor = null;

        if ($predefinedDefinition !== null && $predefinedDefinition->getStaticFactoryMethod() !== null && $predefinedDefinition->getStaticFactoryClass() !== null) {
            $factoryClass = $predefinedDefinition->getStaticFactoryClass();
            $factoryMethod = $predefinedDefinition->getStaticFactoryMethod();
            if (!class_exists($factoryClass)) {
                throw new RuntimeException("Class not found for factory method $factoryClass");
            }

            $factoryClassRef = new ReflectionClass($factoryClass);

            if (!$factoryClassRef->hasMethod($factoryMethod)) {
                throw new RuntimeException("Method {$factoryMethod} not found on class {$factoryClass}");
            }

            $factoryMethodRef = $factoryClassRef->getMethod($factoryMethod);

            if (!$factoryMethodRef->isPublic() || $factoryMethodRef->isAbstract() || !$factoryMethodRef->isStatic()) {
                throw new RuntimeException("Cannot call method {$factoryMethod} on class {$factoryClass}");
            }

            $constructor = $factoryMethodRef;
            $definition->setStaticFactoryMethod($factoryClass, $factoryMethod);
        } else {
            $constructor = $class->getConstructor();
        }

        // Check for Constructor Params that we may not have recorded yet
        // And build up parameter injection tree
        if ($constructor !== null) {
            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                $predefinedParameter = $predefinedDefinition?->getParameter($parameter->name);

                if ($predefinedParameter !== null) {
                    $parameters[] = $predefinedParameter;
                    continue;
                }

                $type = $this->getReflectionClassFromReflectionType($parameter->getType());
                $parameters[] = ParameterDefinition::fromParameter($parameter, $className, $type);

                if ($type !== null && $type->name !== $className) {
                    $this->analyzeClass($type->name, $type);
                }
            }

            $definition->setParameters($parameters);
        }
    }

    public function addService(string $key, ServiceDefinition $definition): void
    {
        $this->analyzeServiceDefinition($key, $definition);
    }
}
