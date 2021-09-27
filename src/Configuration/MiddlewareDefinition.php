<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionException;

class MiddlewareDefinition
{
    public function __construct(private string $className, private int $priority)
    {
    }

    #[Pure]
    public static function create(string $className, int $priority = 0): MiddlewareDefinition
    {
        return new self($className, $priority);
    }

    /**
     * @throws ReflectionException
     *
     * @return ReflectionClass<object>
     */
    public function getReflectionClass(): ReflectionClass
    {
        /**
         * @psalm-suppress ArgumentTypeCoercion It's valid here as we want to throw anyways
         * @phpstan-ignore-next-line
         */
        return new ReflectionClass($this->className);
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
