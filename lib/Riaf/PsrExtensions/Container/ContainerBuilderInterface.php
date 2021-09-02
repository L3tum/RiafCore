<?php

namespace Riaf\PsrExtensions\Container;

use Psr\Container\ContainerInterface;

interface ContainerBuilderInterface extends ContainerInterface
{
    /**
     * Registers a factory under an ID (preferably the class)
     * An Instance of a {@see ContainerInterface} is passed to the Factory.
     */
    public function set(string $id, callable $factory): void;

    /**
     * Returns a {@see ContainerInterface} to reduce the API surface.
     */
    public function buildContainer(): ContainerInterface;
}
