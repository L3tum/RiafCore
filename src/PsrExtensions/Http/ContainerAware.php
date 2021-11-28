<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Http;

use Psr\Container\ContainerInterface;

trait ContainerAware
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }
}
