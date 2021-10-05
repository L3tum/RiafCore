<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Attribute;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;
use RuntimeException;
use Throwable;

class ContainerCompiler extends BaseCompiler
{
    /** @var string[] */
    private array $constructionMethodCache = [];

    /** @var array<string, bool> */
    private array $needsSeparateConstructor = [];

    /** @var array<string, ServiceDefinition|false> */
    private array $services = [];

    public function supportsCompilation(): bool
    {
        return $this->config instanceof ContainerCompilerConfiguration;
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;

        // First collect all project-related classes
        foreach ($this->analyzer->getUsedClasses($this->config->getProjectRoot()) as $class) {
            /* @var ReflectionClass $class */
            $this->analyzeClass($class);
        }

        // Then do a first look-over for ServiceDefinitions
        foreach ($config->getAdditionalClasses() as $key => $value) {
            if ($value instanceof ServiceDefinition) {
                $className = $value->getClassName();
                $this->services[$className] = $value;

                if ($key !== $className) {
                    $this->services[$key] = $value;
                }

                // Go over the defined parameters
                // And for each parameter:
                //      1. Check if it has been recorded as a service
                //      2. Add the parameter to the list for separate methods
                //      3. Check the fallback
                $parameters = $value->getParameters();
                foreach ($parameters as $parameter) {
                    while ($parameter !== null) {
                        if ($parameter->isInjected()) {
                            if (class_exists($parameter->getValue())) {
                                /** @noinspection PhpUnhandledExceptionInspection */
                                $this->analyzeClass(new ReflectionClass($parameter->getValue()));
                            }

                            $this->needsSeparateConstructor[$parameter->getValue()] = true;
                        }

                        $parameter = $parameter->getFallback();
                    }
                }
            }
        }

        // Then go for all string-definitions
        foreach ($config->getAdditionalClasses() as $key => $value) {
            if (!($value instanceof ServiceDefinition)) {
                if (!class_exists($value)) {
                    throw new RuntimeException("Class $value does not exist!");
                }

                $this->analyzeClass(new ReflectionClass($value));

                // Add the key as a name for the service
                if ($key !== $value && isset($this->services[$value])) {
                    $this->services[$key] = $this->services[$value];
                }
            }
        }

        // Add itself to Container
        $ownClass = $config->getContainerNamespace() . '\\Container';
        if (!isset($this->services[$ownClass]) && !isset($this->constructionMethodCache[$ownClass])) {
            $this->services[$ownClass] = new ServiceDefinition($ownClass);
            $this->services[ContainerInterface::class] = $this->services[$ownClass];
            $this->constructionMethodCache[$ownClass] = '$this';
        }

        // Add current Config to Container
        $configClass = (new ReflectionClass($config))->getName();
        if (
            !(new ReflectionClass($config))->isAnonymous()
            && !isset($this->services[$configClass])
        ) {
            $this->services[$configClass] = new ServiceDefinition($configClass);
            $this->services[BaseConfiguration::class] = $this->services[$configClass];
            $this->constructionMethodCache[$configClass] = "new \\$configClass()";
        }

        $this->openResultFile($config->getContainerFilepath());

        $this->generateContainer();

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function analyzeClass(ReflectionClass $class): void
    {
        $className = $class->getName();

        // Skip Abstract Classes, non-instantiable, anonymous, attributes, exceptions and those we already analyzed
        if (
            isset($this->services[$className])
            || $class->isAbstract()
            || $class->isInterface()
            || !$class->isInstantiable()
            || $class->isAnonymous()
            || count($class->getAttributes(Attribute::class)) > 0
            || $class->implementsInterface(Throwable::class)
        ) {
            return;
        }

        $definition = new ServiceDefinition($className);
        $this->services[$className] = $definition;

        // Record class as implementation for interface
        foreach ($class->getInterfaces() as $interface) {
            $interfaceName = $interface->getName();

            if (!isset($this->services[$interfaceName])) {
                $this->services[$interfaceName] = $definition;
            } else {
                $this->services[$interfaceName] = false;
            }
        }

        // Walk the parent-tree upwards to analyze those
        $extensionClass = $class->getParentClass();
        while ($extensionClass !== null && $extensionClass !== false) {
            $extensionClassName = $extensionClass->getName();
            $this->analyzeClass($extensionClass);

            if ($extensionClass->isInterface() || $extensionClass->isAbstract()) {
                if (!isset($this->services[$extensionClassName])) {
                    $this->services[$extensionClassName] = $definition;
                } else {
                    $this->services[$extensionClassName] = false;
                }
            }

            $extensionClass = $extensionClass->getParentClass();
        }

        // Check for Constructor Params that we may not have recorded yet
        // And build up parameter injection tree
        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            $parameters = [];
            foreach ($constructor->getParameters() as $parameter) {
                $param = ParameterDefinition::createInjected($parameter->name, $parameter->name);
                $parameters[] = $param;

                $type = $this->getReflectionClassFromReflectionType($parameter->getType());

                if ($type !== null && $type->name !== $className) {
                    $this->analyzeClass($type);
                    $this->needsSeparateConstructor[$type->name] = true;
                    $param = $param->withFallback(ParameterDefinition::createInjected($parameter->name, $type->name));
                }

                // Default value
                if ($parameter->isDefaultValueAvailable()) {
                    $value = $parameter->getDefaultValue();
                    // Named constant
                    if ($parameter->isDefaultValueConstant() && (is_object($value) || is_array($value))) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $name = $parameter->getDefaultValueConstantName();

                        if ($name !== null) {
                            if (str_starts_with($name, 'self::')) {
                                $name = str_replace('self::', "\\$className::", $name);
                            }

                            $param = $param->withFallback(ParameterDefinition::createNamedConstant($parameter->name, $name));
                        }
                    } else {
                        $value = $parameter->getDefaultValue();

                        if (is_string($value)) {
                            $param = $param->withFallback(ParameterDefinition::createString($parameter->name, $value));
                        } elseif (is_int($value)) {
                            $param = $param->withFallback(ParameterDefinition::createInteger($parameter->name, $value));
                        } elseif (is_float($value)) {
                            $param = $param->withFallback(ParameterDefinition::createFloat($parameter->name, $value));
                        } elseif (null === $value) {
                            $param = $param->withFallback(ParameterDefinition::createNull($parameter->name));
                        } else {
                            $param = $param->withFallback(ParameterDefinition::createSkipIfNotFound($parameter->name));
                        }
                    }
                }
            }

            $definition->setParameters($parameters);
        }
    }

    private function generateContainer(): void
    {
        $this->generateHeader();

        $availableServices = $this->generateContainerGetter();

        $this->generateContainerHasser($availableServices);

        $this->generateSeparateMethods();

        $this->writeLine('}');
    }

    private function generateHeader(): void
    {
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;
        $namespace = $config->getContainerNamespace();
        $this->writeLine('<?php');
        $this->writeLine(
            <<<HEADER
namespace $namespace;

class Container implements \Psr\Container\ContainerInterface
{
    /** @var array<string, object> */
    private array \$instantiatedServices = [];

    /** @throws \Psr\Container\NotFoundExceptionInterface */
    public function get(string \$id)
    {
        return \$this->instantiatedServices[\$id] ?? match (\$id){
HEADER
        );
    }

    /**
     * @return string[]
     */
    private function generateContainerGetter(): array
    {
        /** @var string[] $availableServices */
        $availableServices = [];

        foreach ($this->services as $className => $serviceDefinition) {
            // Skip those where the implementation is not clearly defined
            if ($serviceDefinition === false) {
                continue;
            }

            $method = $this->generateAutowiredConstructor($className, $serviceDefinition);

            // Cannot provide this service, skip it
            if ($method === null) {
                continue;
            }

            $this->writeLine(
                "\"$className\" => $method,",
                3
            );

            $availableServices[] = $className;
        }

        $this->writeLine(
            'default => throw new \\Riaf\\PsrExtensions\\Container\\IdNotFoundException($id)',
            3
        );

        $this->writeLine('};', 2);
        $this->writeLine('}', 1);
        $this->writeLine();

        return $availableServices;
    }

    private function generateAutowiredConstructor(string $key, ServiceDefinition $serviceDefinition): ?string
    {
        $className = $serviceDefinition->getReflectionClass()?->name ?? $serviceDefinition->getClassName();

        if (isset($this->constructionMethodCache[$className])) {
            return $this->constructionMethodCache[$className];
        }

        $parameters = [];

        foreach ($serviceDefinition->getParameters() as $parameter) {
            $getter = $parameter->getName() . ': ';
            $generated = $this->createGetterFromParameter($parameter);
            // We cannot inject a parameter. Therefore we cannot construct the service.
            // Return null
            // TODO: Remove the specific parameter from the usedInConstructor list to stop generating the separate method
            if ($generated === null) {
                return null;
            }
            $parameters[] = $getter . $generated;
        }

        $parameterString = implode(', ', $parameters);
        $method = "\$this->instantiatedServices[\"$key\"] = ";

        // If the key is not the className then it's likely an interface,
        // which means that we need to save the service under the className as well.
        if ($key !== $className) {
            $method .= "\$this->instantiatedServices[\"$className\"] ?? \$this->instantiatedServices[\"$className\"] = ";
        }
        $method .= "new \\$className($parameterString)";

        return $method;
    }

    private function createGetterFromParameter(ParameterDefinition $parameter): string|int|float|null
    {
        if ($parameter->isConstantPrimitive()) {
            return $parameter->getValue();
        } elseif ($parameter->isString()) {
            return '"' . $parameter->getValue() . '"';
        } elseif ($parameter->isNamedConstant()) {
            return $parameter->getValue();
        } elseif ($parameter->isNull()) {
            return 'null';
        } elseif ($parameter->isEnv()) {
            $generated = '$_SERVER["' . $parameter->getValue() . '"]';
            $fallback = $parameter->getFallback();

            if ($fallback !== null) {
                $generatedFallback = $this->createGetterFromParameter($fallback);

                if ($generatedFallback !== null) {
                    $generated .= ' ?? ' . $generatedFallback;
                }
            }

            return $generated;
        } elseif ($parameter->isInjected()) {
            $value = $parameter->getValue();
            if (isset($this->services[$value]) && $this->services[$value] !== false) {
                // Check that we can generate the constructor for this service
                if ($this->generateAutowiredConstructor($value, $this->services[$value]) !== null) {
                    $normalizedName = $this->normalizeClassNameToMethodName($value);
                    $generated = "\$this->instantiatedServices[\"$value\"] ?? \$this->$normalizedName()";
                    $this->needsSeparateConstructor[$value] = true;

                    return $generated;
                }
            } elseif ($parameter->isSkipIfNotFound()) {
                return null;
            }
            $fallback = $parameter->getFallback();

            if ($fallback === null) {
                return null;
            }

            $generated = $this->createGetterFromParameter($fallback);

            if ($generated === null) {
                return null;
            }

            return $generated;
        }

        return null;
    }

    private function normalizeClassNameToMethodName(string $className): string
    {
        return str_replace('\\', '_', $className);
    }

    /**
     * @param string[] $availableServices
     */
    private function generateContainerHasser(array $availableServices): void
    {
        $this->writeLine('/** @var array<string, bool> */', 1);
        $this->writeLine('private const AVAILABLE_SERVICES = [', 1);

        foreach ($availableServices as $availableService) {
            $this->writeLine("\"$availableService\" => true,", 2);
        }

        $this->writeLine('];', 1);
        $this->writeLine();
        $this->writeLine('public function has(string $id): bool', 1);
        $this->writeLine('{', 1);
        $this->writeLine('return isset(self::AVAILABLE_SERVICES[$id]);', 2);
        $this->writeLine('}', 1);
    }

    private function generateSeparateMethods(): void
    {
        $generated = [];

        foreach ($this->needsSeparateConstructor as $className => $_) {
            if (isset($generated[$className])) {
                continue;
            }

            $throws = false;
            $method = null;

            if (isset($this->services[$className]) && $this->services[$className] !== false) {
                $serviceDefinition = $this->services[$className];
                $method = $this->generateAutowiredConstructor($className, $serviceDefinition);
            } else {
                $throws = true;
                $method = "throw new \\Riaf\\PsrExtensions\\Container\\IdNotFoundException(\"$className\")";
            }

            if ($method === null) {
                $throws = true;
                $method = "throw new \\Riaf\\PsrExtensions\\Container\\IdNotFoundException(\"$className\")";
            }

            $normalizedName = $this->normalizeClassNameToMethodName($className);

            if ($throws) {
                $this->writeLine('/** @throws \\Psr\\Container\\NotFoundExceptionInterface */', 1);
            }

            $this->writeLine("public function $normalizedName(): \\$className", 1);
            $this->writeLine('{', 1);
            $this->writeLine("return $method;", 2);
            $this->writeLine('}', 1);

            $generated[$className] = true;
        }
    }
}
