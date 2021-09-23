<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Middleware;

use RuntimeException;

class MiddlewareHint
{
    /**
     * @param class-string $class
     * @param int          $priority
     */
    public function __construct(private string $class, private int $priority)
    {
    }

    /**
     * @throws RuntimeException
     */
    public static function create(string $class, int $priority): MiddlewareHint
    {
        if (!class_exists($class)) {
            throw new RuntimeException("$class not found!");
        }

        return new self($class, $priority);
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
