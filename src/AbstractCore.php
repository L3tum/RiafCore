<?php

declare(strict_types=1);

namespace Riaf;

use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\ContainerCompilerConfiguration;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Configuration\RouterCompilerConfiguration;
use RuntimeException;

abstract class AbstractCore
{
    protected RequestHandlerInterface $requestHandler;

    protected ContainerInterface $container;

    public function __construct(protected BaseConfiguration $config, ?ContainerInterface $container = null)
    {
        // Early exit if we can't find a Container at all
        $this->container = $container ?? $this->fetchContainer() ?? throw new RuntimeException('Missing Container');

        // Only try to fetch Router if the MiddlewareDispatcher could not be found (Router is a Middleware)
        // Or try accessing it from the Container if Router could not be found either
        $this->requestHandler = $this->fetchMiddlewareDispatcher()
            ?? $this->fetchRouter()
            ?? ($this->container->has(RequestHandlerInterface::class) ? $this->container->get(RequestHandlerInterface::class) : null)
            ?? throw new RuntimeException('Missing RequestHandler');
    }

    protected function fetchContainer(): ?ContainerInterface
    {
        if ($this->config instanceof ContainerCompilerConfiguration) {
            $containerClass = $this->config->getContainerNamespace() . '\\Container';
            if (!class_exists($containerClass)) {
                /** @psalm-suppress UnresolvableInclude */
                @include_once $this->config->getProjectRoot() . $this->config->getContainerFilepath();
            }
            if (class_exists($containerClass)) {
                /** @var ContainerInterface */
                return new ('\\' . $containerClass)();
            }
        }

        return null;
    }

    protected function fetchMiddlewareDispatcher(): ?RequestHandlerInterface
    {
        if ($this->config instanceof MiddlewareDispatcherCompilerConfiguration) {
            $middlewareDispatcherClass = $this->config->getMiddlewareDispatcherNamespace() . '\\MiddlewareDispatcher';
            /** @var RequestHandlerInterface */
            return $this->container->has($middlewareDispatcherClass) ? $this->container->get($middlewareDispatcherClass) : null;
        }

        return null;
    }

    protected function fetchRouter(): ?RequestHandlerInterface
    {
        if ($this->config instanceof RouterCompilerConfiguration) {
            $routerClass = $this->config->getRouterNamespace() . '\\Router';
            /** @var RequestHandlerInterface */
            return $this->container->has($routerClass) ? $this->container->get($routerClass) : null;
        }

        return null;
    }

    /**
     * @codeCoverageIgnore
     */
    public function createRequestFromGlobals(): ServerRequestInterface
    {
        /**
         * @var ServerRequestCreatorInterface $creator
         */
        // TODO: Save as private property? Call + Null-Check vs just a null-check I guess?
        $creator = $this->container->get(ServerRequestCreatorInterface::class);

        return $creator->fromGlobals();
    }
}
