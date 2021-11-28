<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use ArrayAccess;
use Closure;
use Exception;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use Riaf\Compiler\BaseCompiler;
use RuntimeException;
use Serializable;

final class ParameterDefinition implements JsonSerializable
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
        private bool $isBool = false,
        private bool $isArray = false,
        private bool $isObject = false,
        private bool $isClosure = false,
        private ?ParameterDefinition $fallback = null
    ) {
    }

    /**
     * @param array{name: string, class: string, env: string, value: mixed, fallback: array<string, mixed>} $parameter
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
            $param = self::fromValue($name, $parameter['value']);
        }

        if (isset($parameter['fallback'])) {
            /** @var array{name: string, class: string, env: string, value: mixed, fallback: array<string, mixed>} $fallback */
            $fallback = $parameter['fallback'];
            $param = $param->withFallback(self::fromArray($fallback));
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

    /**
     * @param string|int|bool|float|object|array<array-key, mixed>|mixed|null $value
     *
     * @return static
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    public static function fromValue(string $name, mixed $value): self
    {
        if (is_string($value)) {
            return self::createString($name, $value);
        } elseif (is_int($value)) {
            return self::createInteger($name, $value);
        } elseif (is_float($value)) {
            return self::createFloat($name, $value);
        } elseif (is_bool($value)) {
            return self::createBool($name, $value);
        } elseif (null === $value) {
            return self::createNull($name);
        } elseif (is_array($value)) {
            $values = [];

            /**
             * @psalm-suppress MixedAssignment
             *
             * @var array-key $key
             * @var mixed     $item
             */
            foreach ($value as $key => $item) {
                $values[] = ['key' => self::fromValue("$key", $key), 'value' => self::fromValue("$key", $item)];
            }

            return self::createArray($name, $values);
        } elseif (is_object($value)) {
            if ($value instanceof Closure) {
                if (!class_exists("Opis\Closure\SerializableClosure")) {
                    throw new RuntimeException('Cannot serialize Closures without opis/closure installed!');
                }

                $parameters = [];
                try {
                    $reflection = new ReflectionFunction($value);
                    foreach ($reflection->getParameters() as $parameter) {
                        $parameters[] = self::fromParameter(
                            $parameter,
                            $reflection->getClosureScopeClass()?->getName() ?? '',
                            BaseCompiler::getReflectionClassFromReflectionType($parameter->getType())
                        );
                    }
                } catch (Exception) {
                    // Intentionally left blank
                }

                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                return self::createClosure(
                    $name,
                    [
                        'closure' => serialize(new \Opis\Closure\SerializableClosure($value)),
                        'parameters' => $parameters,
                    ]
                );
            }

            if (!self::isSerializable($value)) {
                throw new RuntimeException("Cannot serialize value of parameter $name!");
            }

            return self::createObject($name, serialize($value));
        }
        // TODO: Exception
        throw new RuntimeException('Invalid parameter value');
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
    public static function createBool(string $name, bool $value): self
    {
        return new self($name, $value, isBool: true);
    }

    #[Pure]
    public static function createNull(string $name): self
    {
        return new self($name, null, isNull: true);
    }

    /**
     * @param string                                                                  $name
     * @param array<int, array{key: ParameterDefinition, value: ParameterDefinition}> $values
     *
     * @return static
     */
    #[Pure]
    public static function createArray(string $name, array $values): self
    {
        return new self($name, $values, isArray: true);
    }

    /**
     * @param ReflectionClass<object>|null $type
     *
     * @return static
     */
    public static function fromParameter(ReflectionParameter $parameter, string $selfContext, ?ReflectionClass $type = null): self
    {
        $originalParam = $param = ParameterDefinition::createInjected($parameter->name, $parameter->name);

        if ($type !== null) {
            $param = $param->withFallback(ParameterDefinition::createInjected($parameter->name, $type->name));
        }

        // Default value
        if ($parameter->isDefaultValueAvailable()) {
            // Named constant
            if ($parameter->isDefaultValueConstant() && $parameter->getDefaultValueConstantName() !== null) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $name = $parameter->getDefaultValueConstantName();

                if (str_starts_with($name, 'self::')) {
                    $name = str_replace('self::', "\\$selfContext::", $name);
                }

                $param = $param->withFallback(ParameterDefinition::createNamedConstant($parameter->name, $name));
            } else {
                try {
                    $param = $param->withFallback(ParameterDefinition::fromValue($parameter->name, $parameter->getDefaultValue()));
                } catch (Exception) {
                    // Intentionally left blank
                }
            }
        } else {
            // Skip if no default value available and cannot be injected
            $param = $param->withFallback(ParameterDefinition::createSkipIfNotFound($parameter->name));
        }

        return $originalParam;
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

    /**
     * @param string                                                    $name
     * @param array{closure: string, parameters: ParameterDefinition[]} $value
     *
     * @return static
     */
    #[Pure]
    public static function createClosure(string $name, array $value): self
    {
        return new self($name, $value, isClosure: true);
    }

    private static function isSerializable(mixed $value): bool
    {
        if (is_string($value) || is_int($value) || null === $value || is_float($value) || is_bool($value)) {
            return true;
        }

        if (is_array($value)) {
            /**
             * @psalm-suppress MixedAssignment
             */
            foreach ($value as $item) {
                if (!self::isSerializable($item)) {
                    return false;
                }
            }

            return true;
        }

        // Technically $value will always be an object at this point, but Psalm is unhappy.
        if (!is_object($value)) {
            return false;
        }

        $reflection = new ReflectionClass($value);

        // User-defined types are always serializable
        if ($reflection->isUserDefined()) {
            // If all properties are serializable
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                if (!self::isSerializable($property->getValue($value))) {
                    return false;
                }
            }

            return true;
        }

        // ArrayAccess apparently good too
        if ($value instanceof ArrayAccess) {
            return true;
        }

        // Internal objects implementing Serializable are also good
        if ($value instanceof Serializable) {
            return true;
        }

        // Those implementing a magic method are also good I guess
        if ($reflection->hasMethod('__serialize') || $reflection->hasMethod('__sleep')) {
            return true;
        }

        return false;
    }

    #[Pure]
    public static function createObject(string $name, string $value): self
    {
        return new self($name, $value, isObject: true);
    }

    public function isClosure(): bool
    {
        return $this->isClosure;
    }

    public function jsonSerialize()
    {
        return [
            'name' => $this->getName(),
            'value' => json_encode($this->getValue()),
            'fallback' => json_encode($this->getFallback()),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getFallback(): ?ParameterDefinition
    {
        return $this->fallback;
    }

    public function isBool(): bool
    {
        return $this->isBool;
    }

    public function isEnv(): bool
    {
        return $this->isEnv;
    }

    public function isNamedConstant(): bool
    {
        return $this->isNamedConstant;
    }

    public function isString(): bool
    {
        return $this->isString;
    }

    public function isConstantPrimitive(): bool
    {
        return $this->isConstantPrimitive;
    }

    public function isNull(): bool
    {
        return $this->isNull;
    }

    public function isArray(): bool
    {
        return $this->isArray;
    }

    public function isObject(): bool
    {
        return $this->isObject;
    }
}
