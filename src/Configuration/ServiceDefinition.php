<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use JetBrains\PhpStorm\Pure;
use ReflectionClass;

final class ServiceDefinition
{
    /**
     * @var ParameterDefinition[]
     */
    private array $parameters;

    /**
     * @var string[]
     */
    private array $aliases;

    /**
     * @param string                $className
     * @param ParameterDefinition[] $parameters
     * @param string[]              $aliases
     * @param bool                  $singleton
     * @param string|null           $staticFactoryClass
     * @param string|null           $staticFactoryMethod
     */
    public function __construct(
        private string $className,
        array $parameters = [],
        array $aliases = [],
        private bool $singleton = true,
        private ?string $staticFactoryClass = null,
        private ?string $staticFactoryMethod = null
    ) {
        $this->parameters = $parameters;
        $this->aliases = $aliases;
    }

    #[Pure]
    public static function create(string $className): self
    {
        return new self($className);
    }

    /**
     * @return ParameterDefinition[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, array{name: string, class: string, env: string, value: mixed, fallback: array<string, mixed>}>|ParameterDefinition[] $parameters
     *
     * @return $this
     */
    public function setParameters(array $parameters): self
    {
        foreach ($parameters as $parameter) {
            if ($parameter instanceof ParameterDefinition) {
                $this->parameters[] = $parameter;
            } elseif (is_array($parameter)) {
                $this->parameters[] = ParameterDefinition::fromArray($parameter);
            }
        }

        return $this;
    }

    #[Pure]
    public function getParameter(string $name): ?ParameterDefinition
    {
        foreach ($this->parameters as $parameter) {
            /** @var ParameterDefinition $parameter */
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        return null;
    }

    public function addAlias(string $alias): self
    {
        $this->aliases[] = $alias;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * @param string|string[] $aliases
     *
     * @return $this
     */
    public function setAliases(string|array $aliases): self
    {
        $this->aliases = is_string($aliases) ? [$aliases] : $aliases;

        return $this;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public function getReflectionClass(): ?ReflectionClass
    {
        if (class_exists($this->className) || interface_exists($this->className)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return new ReflectionClass($this->className);
        }

        return null;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function setSingleton(bool $singleton): self
    {
        $this->singleton = $singleton;

        return $this;
    }

    public function isSingleton(): bool
    {
        return $this->singleton;
    }

    public function setStaticFactoryMethod(string $staticFactoryClass, string $staticFactoryMethod): self
    {
        $this->staticFactoryClass = $staticFactoryClass;
        $this->staticFactoryMethod = $staticFactoryMethod;

        return $this;
    }

    public function getStaticFactoryMethod(): ?string
    {
        return $this->staticFactoryMethod;
    }

    public function getStaticFactoryClass(): ?string
    {
        return $this->staticFactoryClass;
    }
}
