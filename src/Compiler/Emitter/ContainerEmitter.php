<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use JetBrains\PhpStorm\Pure;
use Riaf\Compiler\ContainerCompiler;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;

class ContainerEmitter extends BaseEmitter
{
    /** @var string[] */
    private array $constructionMethodCache = [];

    /** @var array<string, ServiceDefinition|false> */
    private array $services = [];

    #[Pure]
    public function __construct(BaseConfiguration $config, ContainerCompiler $compiler)
    {
        parent::__construct($config, $compiler);
    }

    /**
     * @param array<string, ServiceDefinition|false> $services
     * @param array<string, string>                  $constructorCache
     *
     * @throws Exception
     */
    public function emitContainer(array &$services, array &$constructorCache): void
    {
        $this->services = &$services;
        $this->constructionMethodCache = &$constructorCache;
        /** @var ContainerCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getContainerFilepath());

        $this->generateHeader($config->getContainerNamespace());
        $availableServices = $this->generateContainerGetter();
        $this->generateContainerHasser($availableServices);
        $this->writeLine('}');
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
        $className = $serviceDefinition->getClassName();

        if (isset($this->constructionMethodCache[$className])) {
            return $this->constructionMethodCache[$className];
        }

        $parameters = [];

        foreach ($serviceDefinition->getParameters() as $parameter) {
            $getter = $parameter->getName() . ': ';
            $generated = $this->createGetterFromParameter($parameter);
            // We cannot inject a parameter. Therefore we cannot construct the service.
            // Return null
            if ($generated === false) {
                return null;
            } elseif ($generated === null) {
                // Skip parameter if it can't be injected and isn't important
                continue;
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

    private function createGetterFromParameter(ParameterDefinition $parameter): string|null|false
    {
        if ($parameter->isConstantPrimitive()) {
            return (string) $parameter->getValue();
        } elseif ($parameter->isString()) {
            return '"' . $parameter->getValue() . '"';
        } elseif ($parameter->isNamedConstant()) {
            return "{$parameter->getValue()}";
        } elseif ($parameter->isNull()) {
            return 'null';
        } elseif ($parameter->isBool()) {
            return $parameter->getValue() ? 'true' : 'false';
        } elseif ($parameter->isEnv()) {
            $generated = '$_SERVER["' . $parameter->getValue() . '"]';
            $fallback = $parameter->getFallback();

            if ($fallback !== null) {
                $generatedFallback = $this->createGetterFromParameter($fallback);

                if ($generatedFallback !== null && $generatedFallback !== false) {
                    $generated .= ' ?? ' . $generatedFallback;
                }
            }

            return $generated;
        } elseif ($parameter->isInjected()) {
            $value = $parameter->getValue();
            if (isset($this->services[$value]) && $this->services[$value] !== false) {
                // Check that we can generate the constructor for this service
                if (($constructor = $this->generateAutowiredConstructor($value, $this->services[$value])) !== null) {
                    return "\$this->instantiatedServices[\"$value\"] ?? $constructor";
                }
            }

            $fallback = $parameter->getFallback();

            if ($fallback === null) {
                return null;
            }

            return $this->createGetterFromParameter($fallback);
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
}
