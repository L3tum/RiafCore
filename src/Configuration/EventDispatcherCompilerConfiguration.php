<?php

declare(strict_types=1);

namespace Riaf\Configuration;

interface EventDispatcherCompilerConfiguration
{
    public function getEventDispatcherNamespace(): string;

    public function getEventDispatcherFilepath(): string;
}
