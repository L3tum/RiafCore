<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

use Psr\Container\ContainerInterface;

class FactoryMethodContainerParameter
{
    public function __construct(public string $creator = 'Constructor')
    {
    }

    public static function create(ContainerInterface $container): self
    {
        return new self('Factory');
    }
}
