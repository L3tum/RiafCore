<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Configuration\PreloadCompilerConfiguration;
use Riaf\Configuration\RouterCompilerConfiguration;
use Riaf\Events\BootEvent;
use Riaf\Events\CoreEvent;

class SampleCompilerConfiguration extends BaseConfiguration implements PreloadCompilerConfiguration, ContainerCompilerConfiguration, RouterCompilerConfiguration, MiddlewareDispatcherCompilerConfiguration
{
    public function getContainerNamespace(): string
    {
        return 'Riaf';
    }

    public function getContainerFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/Container.php';
    }

    public function getRouterNamespace(): string
    {
        return 'Riaf';
    }

    public function getRouterFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/Router.php';
    }

    public function getPreloadingFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/preloading.php';
    }

    public function getAdditionalClasses(): array
    {
        return [
            CoreEvent::class => BootEvent::class,
            AnalyzerInterface::class => StandardAnalyzer::class,
        ];
    }

    public function getAdditionalPreloadedFiles(): array
    {
        return ['bin/compile', 'src/Core.php'];
    }

    public function getMiddlewareDispatcherNamespace(): string
    {
        return 'Riaf';
    }

    public function getMiddlewareDispatcherFilepath(): string
    {
        return '/var/cache/' . ($_SERVER['APP_ENV'] ?? 'dev') . '/MiddlewareDispatcher.php';
    }

    public function getAdditionalMiddlewares(): array
    {
        return [];
    }

    public function getAdditionalRouterClasses(): array
    {
        return [];
    }
}
