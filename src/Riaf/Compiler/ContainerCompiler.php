<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use Riaf\Compiler\Configuration\ContainerCompilerConfiguration;

class ContainerCompiler extends BaseCompiler
{
    /** @var array<string, string|false> */
    private array $interfaceToClassMapping = [];

    /** @var array<string, string[]> */
    private array $classToInterfaceMapping = [];

    /** @var string[] */
    private array $constructionMethods = [];

    public function supportsCompilation(): bool
    {
        return $this->config instanceof ContainerCompilerConfiguration;
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);

        if ($this->config instanceof ContainerCompilerConfiguration) {
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

            $ownClass = $config->getContainerNamespace() . '\\Container';
            if (!isset($this->constructionMethods[$ownClass])) {
                $this->constructionMethods[$ownClass] = '$this';

                if (!isset($this->interfaceToClassMapping[ContainerInterface::class])) {
                    $this->interfaceToClassMapping[ContainerInterface::class] = $ownClass;
                    $this->classToInterfaceMapping[$ownClass] = [ContainerInterface::class];
                }
            }

            $handle = $this->openResultFile($config->getContainerFilepath());

            $this->generateContainer($handle);
        }

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function analyzeClass(ReflectionClass $class): void
    {
        if ($class->isInterface() || $class->isAbstract()) {
            return;
        }

        $className = $class->getName();

        // Record class as implementation for interface
        foreach ($class->getInterfaces() as $interface) {
            if (!$interface->isUserDefined()) {
                continue;
            }

            $interfaceName = $interface->getName();

            if (!isset($this->classToInterfaceMapping[$className])) {
                $this->classToInterfaceMapping[$className] = [];
            }

            $this->classToInterfaceMapping[$className][] = $interfaceName;

            if (!isset($this->interfaceToClassMapping[$interfaceName])) {
                $this->interfaceToClassMapping[$interfaceName] = $className;
            } else {
                $this->interfaceToClassMapping[$interfaceName] = false;
            }
        }

        // Autowiring
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

                $getter = "$parameter->name: (\$this->has(\"$parameter->name\") ? \$this->get(\"$parameter->name\")";
                $typeClass = $this->getReflectionClassFromReflectionType($parameter->getType());
                if ($typeClass !== null) {
                    $getter .= " : \$this->get(\"$typeClass->name\"))";
                } else {
                    $getter .= " : throw new \Riaf\PsrExtensions\Container\IdNotFoundException(\"$parameter->name\"))";
                }

                $parameters[] = $getter;
            }
        }

        $parameterString = implode(', ', $parameters);
        $this->constructionMethods[$className] =
            <<<FUNCTION
new \\$className($parameterString)
FUNCTION;
    }

    /**
     * @param resource $handle
     */
    private function generateContainer(&$handle): void
    {
        $this->generateHeader($handle);

        $availableServices = $this->generateContainerGetter($handle);

        $this->generateContainerHasser($handle, $availableServices);

        $this->writeLine($handle, '}');
    }

    /** @param resource $handle */
    private function generateHeader(&$handle): void
    {
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;
        $namespace = $config->getContainerNamespace();
        $this->writeLine($handle, '<?php');
        fwrite(
            $handle,
            <<<HEADER
namespace $namespace;

use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /** @var array<string, object> */
    private array \$instantiatedServices = [];

    public function get(string \$id)
    {
        return \$this->instantiatedServices[\$id] ?? \$this->instantiatedServices[\$id] = match (\$id){

HEADER
        );
    }

    /**
     * @param resource $handle
     *
     * @return string[]
     */
    private function generateContainerGetter(&$handle): array
    {
        /** @var string[] $availableServices */
        $availableServices = [];

        foreach ($this->constructionMethods as $key => $method) {
            $interfaces = $this->classToInterfaceMapping[$key] ?? [];

            foreach ($interfaces as $interface) {
                if ($interface !== $key && $this->interfaceToClassMapping[$interface] === $key) {
                    $this->writeLine(
                        $handle,
                        "\"$interface\" => \$this->instantiatedServices[\"$key\"] ?? \$this->instantiatedServices[\"$key\"] = $method,",
                        3
                    );
                    $availableServices[] = $interface;
                }
            }

            $this->writeLine($handle, "\"$key\" => $method,", 3);
            $availableServices[] = $key;
        }

        $this->writeLine(
            $handle,
            'default => throw new \\Riaf\\PsrExtensions\\Container\\IdNotFoundException($id)',
            3
        );

        $this->writeLine($handle, '};', 2);
        $this->writeLine($handle, '}', 1);
        $this->writeLine($handle);

        return $availableServices;
    }

    /**
     * @param resource $handle
     * @param string[] $availableServices
     */
    private function generateContainerHasser(&$handle, array $availableServices): void
    {
        $this->writeLine($handle, '/** @var array<string, bool> */', 1);
        $this->writeLine($handle, 'private const AVAILABLE_SERVICES = [', 1);

        foreach ($availableServices as $availableService) {
            $this->writeLine($handle, "\"$availableService\" => true,", 2);
        }

        $this->writeLine($handle, '];', 1);
        $this->writeLine($handle);
        $this->writeLine($handle, 'public function has(string $id): bool', 1);
        $this->writeLine($handle, '{', 1);
        $this->writeLine($handle, 'return isset(self::AVAILABLE_SERVICES[$id]);', 2);
        $this->writeLine($handle, '}', 1);
    }
}
