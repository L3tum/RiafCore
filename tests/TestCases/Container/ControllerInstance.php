<?php

declare(strict_types=1);

namespace Riaf\TestCases\Container;

use Riaf\Configuration\BaseConfiguration;
use Riaf\PsrExtensions\Http\BaseController;

class ControllerInstance extends BaseController
{
    public function __construct(private BaseConfiguration $config)
    {
    }

    public function hasContainer(): bool
    {
        return $this->container !== null;
    }
}
