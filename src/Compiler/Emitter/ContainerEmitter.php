<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use ReflectionClass;
use Riaf\Compiler\PreloadingCompiler;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Helper\InheritanceHelper;
use Riaf\PsrExtensions\Http\ContainerAware;
use RuntimeException;

class ContainerEmitter extends BaseEmitter
{
    /** @var string[] */
    private array $constructionMethodCache = [];

    /** @var array<string, ServiceDefinition|false> */
    private array $services = [];

    /**
     * @var array<string, true>
     */
    private array $manuallyAddedServices = [];

    /**
     * @param array<string, ServiceDefinition|false> $services
     * @param array<string, string>                  $constructorCache
     * @param array<string, true>                    $manuallyAddedServices
     *
     * @throws Exception
     */
    public function emitContainer(array &$services, array &$constructorCache, array &$manuallyAddedServices, PreloadingCompiler $preloadingCompiler): void
    {
        $this->services = &$services;
        $this->constructionMethodCache = &$constructorCache;
        $this->manuallyAddedServices = &$manuallyAddedServices;
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getContainerFilepath());

        $this->generateHeader($config->getContainerNamespace());
        $availableServices = $this->generateContainerGetter();
        $this->generateContainerHasser($availableServices);
        $this->writeLine('}');

        if ($preloadingCompiler->supportsCompilation()) {
            $preloadingCompiler->addAdditionalServices($availableServices);
            $preloadingCompiler->compile();
        }
    }

    private function generateHeader(string $namespace): void
    {
        $this->writeLine('<?php');
        $this->writeLine(
            <<<HEADER
namespace $namespace;

class Container implements \Psr\Container\ContainerInterface
{
    /** @var array<string, object> */
    private array \$instantiatedServices = [];

    /** {@inheritDoc} */
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

        foreach ($this->services as $key => $serviceDefinition) {
            // Skip those where the implementation is not clearly defined
            if ($serviceDefinition === false) {
                continue;
            }

            $method = $this->generateAutowiredConstructor($key, $serviceDefinition);

            // Cannot provide this service, skip it
            if ($method === null) {
                if (isset($this->manuallyAddedServices[$key])) {
                    // TODO: Exception
                    throw new RuntimeException("Cannot provide Service $key");
                }
                continue;
            }

            $this->writeLine(
                "\"$key\" => $method,",
                3
            );

            $availableServices[] = $key;
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

    /**
     * @param array<string, true> $resolvingStack
     */
    private function generateAutowiredConstructor(string $key, ServiceDefinition $serviceDefinition, array &$resolvingStack = []): ?string
    {
        $className = $serviceDefinition->getClassName();

        if (isset($this->constructionMethodCache[$className])) {
            return $this->constructionMethodCache[$className];
        }

        if (!class_exists($className)) {
            throw new RuntimeException("Cannot provide Service $className that does not exist!");
        }

        if (isset($resolvingStack[$className])) {
            $this->logger->info("$className contains a cyclic dependency");

            return null;
        }
        $resolvingStack[$className] = true;

        $parameters = [];
        $parameterNames = [];
        $skippedAnyParameter = false;

        foreach ($serviceDefinition->getParameters() as $parameter) {
            $generated = $this->createGetterFromParameter($parameter, $resolvingStack);
            // We cannot inject a parameter. Therefore we cannot construct the service.
            // Return null
            if ($generated === false) {
                $this->logger->info("Could not generate constructor for $className because of {$parameter->getName()}");
                unset($resolvingStack[$className]);

                return null;
            } elseif ($generated === null) {
                // Skip parameter if it can't be injected and isn't important
                $skippedAnyParameter = true;
                continue;
            }
            $parameters[] = $generated;
            $parameterNames[] = $parameter->getName();
        }

        // If we skipped any parameter, then we need to explicitly set them by name
        // Else we can avoid that 0,005μs and 0,2kb overhead
        if ($skippedAnyParameter) {
            foreach ($parameters as $key => $value) {
                $parameters[$key] = $parameterNames[$key] . ': ' . $value;
            }
        }

        $parameterString = implode(', ', $parameters);

        if ($serviceDefinition->isSingleton()) {
            $method = "\$this->instantiatedServices[\"$key\"] = ";

            // If the key is not the className then it's likely an interface,
            // which means that we need to save the service under the className as well.
            // Beware though of classNames that are defined multiple times, which is why we check Services for false
            if ($key !== $className && (!isset($this->services[$className]) || $this->services[$className] !== false)) {
                $method .= "\$this->instantiatedServices[\"$className\"] ?? \$this->instantiatedServices[\"$className\"] = ";
            }
        } else {
            $method = '';
        }

        if ($serviceDefinition->getStaticFactoryClass() !== null && $serviceDefinition->getStaticFactoryMethod() !== null) {
            $constructor = "\\{$serviceDefinition->getStaticFactoryClass()}::{$serviceDefinition->getStaticFactoryMethod()}($parameterString)";
        } else {
            $constructor = "new \\$className($parameterString)";
        }

        if (InheritanceHelper::usesTrait(ContainerAware::class, new ReflectionClass($className))) {
            $constructor = "($constructor)->setContainer(\$this)";
        }

        $method .= $constructor;

        unset($resolvingStack[$className]);

        return $method;
    }

    /**
     * @param array<string, true> $resolvingStack
     */
    private function createGetterFromParameter(ParameterDefinition $parameter, array &$resolvingStack = []): string|null|false
    {
        if ($parameter->isConstantPrimitive()) {
            return (string) $parameter->getValue();
        } elseif ($parameter->isString()) {
            /** @var string $value */
            $value = $parameter->getValue();

            return '"' . $value . '"';
        } elseif ($parameter->isNamedConstant()) {
            /** @var string $value */
            $value = $parameter->getValue();

            return "$value";
        } elseif ($parameter->isNull()) {
            return 'null';
        } elseif ($parameter->isBool()) {
            return $parameter->getValue() ? 'true' : 'false';
        } elseif ($parameter->isEnv()) {
            /** @var string $value */
            $value = $parameter->getValue();
            $generated = '$_SERVER["' . $value . '"]';
            $fallback = $parameter->getFallback();

            if ($fallback !== null) {
                $generatedFallback = $this->createGetterFromParameter($fallback, $resolvingStack);

                if ($generatedFallback !== null && $generatedFallback !== false) {
                    $generated .= ' ?? ' . $generatedFallback;
                }
            }

            return $generated;
        } elseif ($parameter->isInjected()) {
            /** @var string $value */
            $value = $parameter->getValue();
            if (isset($this->services[$value]) && $this->services[$value] !== false) {
                // Check that we can generate the constructor for this service
                if (($constructor = $this->generateAutowiredConstructor($value, $this->services[$value], $resolvingStack)) !== null) {
                    return "\$this->instantiatedServices[\"$value\"] ?? $constructor";
                }
            }

            $fallback = $parameter->getFallback();

            if ($fallback === null) {
                return null;
            }

            return $this->createGetterFromParameter($fallback, $resolvingStack);
        } elseif ($parameter->isArray()) {
            $generated = '';
            /** @var array<array{key: ParameterDefinition, value: ParameterDefinition}> $values */
            $values = $parameter->getValue();
            foreach ($values as $packed) {
                $key = $this->createGetterFromParameter($packed['key'], $resolvingStack);
                $value = $this->createGetterFromParameter($packed['value'], $resolvingStack);

                if ($key === null || $value === null) {
                    return null;
                } elseif ($key === false || $value === false) {
                    return false;
                }

                $generated .= $key . ' => ' . $value . ', ';
            }

            return "[$generated]";
        } elseif ($parameter->isObject()) {
            return "\unserialize('{$parameter->getValue()}')";
        } elseif ($parameter->isClosure()) {
            /** @var array{closure: string, parameters: array<ParameterDefinition>} $value */
            $value = $parameter->getValue();
            $closure = $value['closure'];
            $parameters = $value['parameters'];
            $getters = [];

            foreach ($parameters as $parameter) {
                $getter = $this->createGetterFromParameter($parameter, $resolvingStack);

                if ($getter === null) {
                    return null;
                } elseif ($getter === false) {
                    return false;
                }

                $getters[] = $getter;
            }
            $param = implode(', ', $getters);

            return "((\unserialize('{$closure}'))->getClosure())($param)";
        } elseif ($parameter->isSkipIfNotFound()) {
            return false;
        }

        return null;
    }

    /**
     * @param string[] $availableServices
     */
    private function generateContainerHasser(array $availableServices): void
    {
        $this->writeLine('/** {@inheritDoc} */', 1);
        $this->writeLine('public function has(string $id): bool', 1);
        $this->writeLine('{', 1);
        $this->writeLine('return match($id)', 2);
        $this->writeLine('{', 2);
        $lastOne = array_key_last($availableServices);
        foreach ($availableServices as $key => $availableService) {
            if ($key === $lastOne) {
                $this->writeLine("\"$availableService\" => true,", 3);
            } else {
                $this->writeLine("\"$availableService\",", 3);
            }
        }
        $this->writeLine('default => false', 3);
        $this->writeLine('};', 2);
        $this->writeLine('}', 1);
    }
}
