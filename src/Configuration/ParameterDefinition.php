<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

final class ParameterDefinition
{
    public function __construct(
        private string $name,
        private mixed $value,
        private bool $isConstantPrimitive = false,
        private bool $isString = false,
        private bool $isEnv = false,
        private bool $isInjected = false,
        private bool $isNamedConstant = false,
        private bool $skipIfNotFound = false,
        private bool $isNull = false,
        private ?ParameterDefinition $fallback = null
    ) {
    }

    /**
     * @param array<string, mixed> $parameter
     *
     * @return ParameterDefinition
     */
    public static function fromArray(array $parameter): self
    {
        $name = $parameter['name'];

        if (isset($parameter['class'])) {
            $param = ParameterDefinition::createInjected($name, $parameter['class']);
        } elseif (isset($parameter['env'])) {
            $param = ParameterDefinition::createEnv($name, $parameter['env']);
        } else {
            $value = $parameter['value'];

            if (is_string($value)) {
                $param = ParameterDefinition::createString($name, $value);
            } elseif (is_int($value)) {
                $param = ParameterDefinition::createInteger($name, $value);
            } elseif (is_float($value)) {
                $param = ParameterDefinition::createFloat($name, $value);
            } elseif (null === $value) {
                $param = ParameterDefinition::createNull($name);
            } else {
                // TODO: Exception
                throw new RuntimeException('Invalid parameter value');
            }
        }

        if (isset($parameter['fallback'])) {
            $param = $param->withFallback(self::fromArray($parameter['fallback']));
        }

        return $param;
    }

    #[Pure]
    public static function createInjected(string $name, string $value): self
    {
        return new self($name, $value, isInjected: true);
    }

    #[Pure]
    public static function createEnv(string $name, string $value): self
    {
        return new self($name, $value, isEnv: true);
    }

    #[Pure]
    public static function createString(string $name, string $value): self
    {
        return new self($name, $value, isString: true);
    }

    #[Pure]
    public static function createInteger(string $name, int $value): self
    {
        return new self($name, $value, true);
    }

    #[Pure]
    public static function createFloat(string $name, float $value): self
    {
        return new self($name, $value, true);
    }

    #[Pure]
    public static function createNull(string $name): self
    {
        return new self($name, null, isNull: true);
    }

    public function withFallback(ParameterDefinition $fallback): self
    {
        if ($fallback->isSkipIfNotFound() && !$this->isInjected()) {
            // TODO: Exception
            throw new RuntimeException();
        }

        $this->fallback = $fallback;

        return $this->fallback;
    }

    public function isSkipIfNotFound(): bool
    {
        return $this->skipIfNotFound;
    }

    public function isInjected(): bool
    {
        return $this->isInjected;
    }

    #[Pure]
    public static function createNamedConstant(string $name, string $value): self
    {
        return new self($name, $value, isNamedConstant: true);
    }

    #[Pure]
    public static function createSkipIfNotFound(string $name): self
    {
        return new self($name, false, skipIfNotFound: true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function isConstantPrimitive(): bool
    {
        return $this->isConstantPrimitive;
    }

    public function isString(): bool
    {
        return $this->isString;
    }

    public function isEnv(): bool
    {
        return $this->isEnv;
    }

    public function isNamedConstant(): bool
    {
        return $this->isNamedConstant;
    }

    public function getFallback(): ?ParameterDefinition
    {
        return $this->fallback;
    }

    public function isNull(): bool
    {
        return $this->isNull;
    }
}
