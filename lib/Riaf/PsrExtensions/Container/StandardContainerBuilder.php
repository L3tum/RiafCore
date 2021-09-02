<?php

namespace Riaf\PsrExtensions\Container;

use Psr\Container\ContainerInterface;

class StandardContainerBuilder implements ContainerBuilderInterface
{
    /** @var callable[] */
    protected array $services = [];
    /** @var object[] */
    protected array $instantiatedServices = [];

    /** {@inheritdoc} */
    public function set(string $id, callable $factory): void
    {
        $this->services[$id] = $factory;
    }

    /** {@inheritdoc} */
    public function buildContainer(): ContainerInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws IdNotFoundException
     */
    public function get(string $id)
    {
        if ($this->has($id)) {
            return $this->instantiatedServices[$id] ?? $this->instantiatedServices[$id] = $this->services[$id]($this);
        }

        throw new IdNotFoundException();
    }

    /** {@inheritdoc} */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
