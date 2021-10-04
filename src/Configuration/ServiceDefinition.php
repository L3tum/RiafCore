<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use JetBrains\PhpStorm\Pure;
use ReflectionClass;

final class ServiceDefinition
{
    public function __construct(
        private string $className,
        /** @var ParameterDefinition[] */
        private array $parameters = []
    ) {
    }

    #[Pure]
    public static function create(string $className): self
    {
        return new self($className);
    }

    /**
     * @param array<string, array>|ParameterDefinition[] $parameters
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

    /**
     * @return ParameterDefinition[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
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
}
