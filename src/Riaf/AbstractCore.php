<?php

declare(strict_types=1);

namespace Riaf;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\Compiler\CompilerConfiguration;
use Riaf\Compiler\Configuration\ContainerCompilerConfiguration;
use Riaf\Compiler\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Compiler\Configuration\RouterCompilerConfiguration;
use RuntimeException;

abstract class AbstractCore
{
    protected ?RequestHandlerInterface $requestHandler = null;

    public function __construct(protected CompilerConfiguration $config, protected ?ContainerInterface $container = null)
    {
        $this->fetchContainer();
        $this->fetchMiddlewareDispatcher();
        $this->fetchRouter();

        if ($this->container === null) {
            // TODO: Exception
            throw new RuntimeException('Missing Container');
        }

        // Try accessing it from the Container
        if ($this->requestHandler === null) {
            if ($this->container->has(RequestHandlerInterface::class)) {
                $this->requestHandler = $this->container->get(RequestHandlerInterface::class);
            } else {
                // TODO: Exception
                throw new RuntimeException('Missing RequestHandler');
            }
        }
    }

    protected function fetchContainer(): void
    {
        if ($this->config instanceof ContainerCompilerConfiguration && $this->container === null) {
            $containerClass = '\\' . $this->config->getContainerNamespace() . '\\Container';
            if (class_exists($containerClass)) {
                $this->container = new $containerClass();
            }
        }
    }

    protected function fetchMiddlewareDispatcher(): void
    {
        if ($this->config instanceof MiddlewareDispatcherCompilerConfiguration && $this->container !== null) {
            $middlewareDispatcherClass = '\\' . $this->config->getMiddlewareDispatcherNamespace() . '\\MiddlewareDispatcher';
            if (class_exists($middlewareDispatcherClass)) {
                $this->requestHandler = new $middlewareDispatcherClass($this->container);
            }
        }
    }

    protected function fetchRouter(): void
    {
        if ($this->config instanceof RouterCompilerConfiguration && $this->requestHandler === null && $this->container !== null) {
            $routerClass = '\\' . $this->config->getRouterNamespace() . '\\Router';
            if (class_exists($routerClass)) {
                $this->requestHandler = new $routerClass($this->container);
            }
        }
    }
}
