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
    protected ?RequestHandlerInterface $requestHandler = null;

    public function __construct(protected BaseConfiguration $config, protected ?ContainerInterface $container = null)
    {
        $this->fetchContainer();

        // Early exit if we can't find a Container at all
        if ($this->container === null) {
            // TODO: Exception
            throw new RuntimeException('Missing Container');
        }

        $this->fetchMiddlewareDispatcher();

        // Only try to fetch Router if the MiddlewareDispatcher could not be found (Router is a Middleware)
        if ($this->requestHandler === null) {
            $this->fetchRouter();

            // Try accessing it from the Container if Router could not be found either
            if ($this->requestHandler === null) {
                if ($this->container->has(RequestHandlerInterface::class)) {
                    $this->requestHandler = $this->container->get(RequestHandlerInterface::class);
                } else {
                    // TODO: Exception
                    throw new RuntimeException('Missing RequestHandler');
                }
            }
        }
    }

    protected function fetchContainer(): void
    {
        if ($this->config instanceof ContainerCompilerConfiguration && $this->container === null) {
            $containerClass = $this->config->getContainerNamespace() . '\\Container';
            if (!class_exists($containerClass)) {
                @include_once $this->config->getProjectRoot() . $this->config->getContainerFilepath();
            }
            if (class_exists($containerClass)) {
                /** @psalm-suppress PropertyTypeCoercion Can only be Container */
                $this->container = new ('\\' . $containerClass)();
            }
        }
    }

    protected function fetchMiddlewareDispatcher(): void
    {
        if ($this->config instanceof MiddlewareDispatcherCompilerConfiguration && $this->container !== null) {
            $middlewareDispatcherClass = $this->config->getMiddlewareDispatcherNamespace() . '\\MiddlewareDispatcher';
            if (!class_exists($middlewareDispatcherClass)) {
                @include_once $this->config->getProjectRoot() . $this->config->getMiddlewareDispatcherFilepath();
            }
            if (class_exists($middlewareDispatcherClass)) {
                /** @psalm-suppress PropertyTypeCoercion Can only be RequestHandlerInterface */
                $this->requestHandler = new ('\\' . $middlewareDispatcherClass)($this->container);
            }
        }
    }

    protected function fetchRouter(): void
    {
        if ($this->config instanceof RouterCompilerConfiguration && $this->requestHandler === null && $this->container !== null) {
            $routerClass = $this->config->getRouterNamespace() . '\\Router';
            if (!class_exists($routerClass)) {
                @include_once $this->config->getProjectRoot() . $this->config->getRouterFilepath();
            }
            if (class_exists($routerClass)) {
                /** @psalm-suppress PropertyTypeCoercion Can only be RequestHandlerInterface */
                $this->requestHandler = new ('\\' . $routerClass)($this->container);
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function createRequestFromGlobals(): ServerRequestInterface
    {
        /**
         * @var ServerRequestCreatorInterface $creator
         * @psalm-suppress PossiblyNullReference It's checked in Constructor and an exception thrown if null.
         */
        // TODO: Save as private property? Call + Null-Check vs just a null-check I guess?
        $creator = $this->container->get(ServerRequestCreatorInterface::class);

        return $creator->fromGlobals();
    }
}
