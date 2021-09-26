<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Attribute;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Riaf\Compiler\Configuration\ContainerCompilerConfiguration;
use RuntimeException;
use Throwable;

class ContainerCompiler extends BaseCompiler
{
    /** @var array<string, string|false> */
    private array $interfaceToClassMapping = [];

    /** @var array<string, string[]> */
    private array $classToInterfaceMapping = [];

    /** @var string[] */
    private array $constructionMethods = [];

    /** @var string[] */
    private array $needsSeparateMethod = [];

    public function supportsCompilation(): bool
    {
        return $this->config instanceof ContainerCompilerConfiguration;
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;
        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot());

        foreach ($classes as $class) {
            /* @var ReflectionClass $class */
            $this->analyzeClass($class);
        }

        foreach ($config->getAdditionalClasses() as $key => $value) {
            if (class_exists($value)) {
                $this->analyzeClass(new ReflectionClass($value));
            }

            if ($key !== $value) {
                $this->interfaceToClassMapping[$key] = $value;
                if (!in_array($key, $this->classToInterfaceMapping[$value], true)) {
                    $this->classToInterfaceMapping[$value][] = $key;
                }
            }
        }

        // Add itself to Container
        $ownClass = $config->getContainerNamespace() . '\\Container';
        if (!isset($this->classToInterfaceMapping[$ownClass]) && !isset($this->constructionMethods[$ownClass])) {
            $this->constructionMethods[$ownClass] = '$this';

            if (!isset($this->interfaceToClassMapping[ContainerInterface::class])) {
                $this->interfaceToClassMapping[ContainerInterface::class] = $ownClass;
                $this->classToInterfaceMapping[$ownClass] = [ContainerInterface::class];
            }
        }

        // Add current Config to Container
        $configClass = (new ReflectionClass($config))->getName();
        if (
            !(new ReflectionClass($config))->isAnonymous()
            && !isset($this->classToInterfaceMapping[$configClass])
            && !isset($this->constructionMethods[$configClass])
        ) {
            $this->constructionMethods[$configClass] = "new $configClass()";

            if (!isset($this->interfaceToClassMapping[CompilerConfiguration::class])) {
                $this->interfaceToClassMapping[CompilerConfiguration::class] = $configClass;
                $this->classToInterfaceMapping[$configClass] = [CompilerConfiguration::class];
            }
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

        // Skip Abstract Classes, non-userdefined, anonymous, attributes, exceptions and those we already analyzed
        if (
            $class->isAbstract()
            || $class->isInterface()
            || !$class->isUserDefined()
            || $class->isAnonymous()
            || count($class->getAttributes(Attribute::class)) > 0
            || $class->implementsInterface(Throwable::class)
            || isset($this->classToInterfaceMapping[$className])
        ) {
            return;
        }

        $this->classToInterfaceMapping[$className] = [];

        // Record class as implementation for interface
        foreach ($class->getInterfaces() as $interface) {
            if (!$interface->isUserDefined()) {
                continue;
            }

            $interfaceName = $interface->getName();
            $this->classToInterfaceMapping[$className][] = $interfaceName;

            if (!isset($this->interfaceToClassMapping[$interfaceName])) {
                $this->interfaceToClassMapping[$interfaceName] = $className;
            } else {
                $this->interfaceToClassMapping[$interfaceName] = false;
            }
        }

        // Walk the parent-tree upwards to analyze those
        $extensionClass = $class->getParentClass();
        while ($extensionClass !== null && $extensionClass !== false) {
            $this->analyzeClass($extensionClass);

            if ($extensionClass->isInterface() || $extensionClass->isAbstract()) {
                if ($extensionClass->isUserDefined()) {
                    $extensionClassName = $extensionClass->getName();
                    $this->classToInterfaceMapping[$className][] = $extensionClassName;
                    if (!isset($this->interfaceToClassMapping[$extensionClassName])) {
                        $this->interfaceToClassMapping[$extensionClassName] = $className;
                    } else {
                        $this->interfaceToClassMapping[$extensionClassName] = false;
                    }
                }
            }

            $extensionClass = $extensionClass->getParentClass();
        }

        // Check for Constructor Params that we may not have recorded yet
        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $type = $this->getReflectionClassFromReflectionType($parameter->getType());

                if ($type !== null) {
                    $this->analyzeClass($type);
                }
            }
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
        return \$this->instantiatedServices[\$id] ?? \$this->instantiatedServices[\$id] = match (\$id){
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

        foreach ($this->classToInterfaceMapping as $className => $interfaceNames) {
            $method = $this->generateAutowiredConstructor($className);

            foreach ($interfaceNames as $interfaceName) {
                if ($interfaceName !== $className && $this->interfaceToClassMapping[$interfaceName] === $className) {
                    if (isset($this->needsSeparateMethod[$className])) {
                        $normalizedName = $this->normalizeClassNameToMethodName($className);
                        $this->writeLine(
                            "\"$interfaceName\" => \$this->instantiatedServices[\"$className\"] ?? \$this->$normalizedName(),",
                            3
                        );
                    } else {
                        $this->writeLine(
                            "\"$interfaceName\" => \$this->instantiatedServices[\"$className\"] ?? \$this->instantiatedServices[\"$className\"] = $method,",
                            3
                        );
                    }

                    $availableServices[] = $interfaceName;
                }
            }

            $this->writeLine("\"$className\" => $method,", 3);
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

    private function generateAutowiredConstructor(string $className): string
    {
        if (!class_exists($className)) {
            if (isset($this->constructionMethods[$className])) {
                return $this->constructionMethods[$className];
            }
            // TODO: Exception
            throw new RuntimeException($className);
        }

        $class = new ReflectionClass($className);

        $parameters = [];
        $constructor = $class->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isDefaultValueAvailable()) {
                    if ($parameter->isDefaultValueConstant()) {
                        $defaultValue = $parameter->getDefaultValue();

                        if (is_string($defaultValue)) {
                            $defaultValue = "\"$defaultValue\"";
                        }
                        $parameters[] = "$parameter->name: $defaultValue";
                    }
                    continue;
                }

                if (isset($this->classToInterfaceMapping[$parameter->name])) {
                    $normalizedName = $this->normalizeClassNameToMethodName($parameter->name);
                    $getter = "$parameter->name: \$this->instantiatedServices[\"$parameter->name\"] ?? \$this->$normalizedName()";
                    $this->needsSeparateMethod[] = $parameter->name;
                } else {
                    $typeClass = $this->getReflectionClassFromReflectionType($parameter->getType());

                    if ($typeClass !== null && (isset($this->classToInterfaceMapping[$typeClass->name]) || isset($this->interfaceToClassMapping[$typeClass->name]))) {
                        $normalizedName = $this->normalizeClassNameToMethodName($typeClass->name);
                        $getter = "$parameter->name: \$this->instantiatedServices[\"$typeClass->name\"] ?? \$this->$normalizedName()";
                        $this->needsSeparateMethod[] = $typeClass->name;
                    } else {
                        $getter = "$parameter->name: throw new \Riaf\PsrExtensions\Container\IdNotFoundException(\"$parameter->name\")";
                    }
                }

                $parameters[] = $getter;
            }
        }

        $parameterString = implode(', ', $parameters);
        $method =
            <<<FUNCTION
new \\$className($parameterString)
FUNCTION;
        $this->constructionMethods[$className] = $method;

        return $method;
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

        foreach ($this->needsSeparateMethod as $className) {
            if (isset($generated[$className])) {
                continue;
            }

            $throws = false;

            if (!isset($this->classToInterfaceMapping[$className])) {
                if (isset($this->interfaceToClassMapping[$className]) && $this->interfaceToClassMapping[$className]) {
                    $method = $this->generateAutowiredConstructor($this->interfaceToClassMapping[$className]);
                } else {
                    $throws = true;
                    $method = "throw new \\Riaf\\PsrExtensions\\Container\\IdNotFoundException(\"$className\")";
                }
            } else {
                $method = $this->generateAutowiredConstructor($className);
            }

            $normalizedName = $this->normalizeClassNameToMethodName($className);

            if ($throws) {
                $this->writeLine('/** @throws \\Psr\\Container\\NotFoundExceptionInterface */', 1);
            }

            $this->writeLine("public function $normalizedName(): \\$className", 1);
            $this->writeLine('{', 1);
            $this->writeLine("return \$this->instantiatedServices[\"$className\"] = $method;", 2);
            $this->writeLine('}', 1);

            $generated[$className] = true;
        }
    }
}
