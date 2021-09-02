<?php

namespace Riaf;

use JetBrains\PhpStorm\Pure;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use Riaf\PsrExtensions\Container\ContainerBuilderInterface;
use Riaf\PsrExtensions\Middleware\MiddlewareDispatcherInterface;
use Riaf\ResponseEmitter\ResponseEmitterInterface;
use Riaf\ResponseEmitter\StandardResponseEmitter;

class CoreBuilder
{
    protected ContainerBuilderInterface $container;
    protected MiddlewareDispatcherInterface $middlewareDispatcher;

    #[Pure]
    public function __construct(ContainerBuilderInterface $containerBuilder, MiddlewareDispatcherInterface $middlewareDispatcher)
    {
        $this->container = $containerBuilder;
        $this->middlewareDispatcher = $middlewareDispatcher;
    }

    public function buildCore(): Core
    {
        if (!$this->container->has(ResponseEmitterInterface::class)) {
            $this->container->set(
                ResponseEmitterInterface::class,
                static function () {
                    return new StandardResponseEmitter();
                }
            );
        }

        $container = $this->container->buildContainer();

        return new Core($container, $this->middlewareDispatcher);
    }

    public function withServiceFactory(string $id, callable $factory): self
    {
        $this->container->set($id, $factory);

        return $this;
    }

    public function withService(string $id, object $service): self
    {
        $this->container->set(
            $id,
            static function () use ($service) {
                return $service;
            }
        );

        return $this;
    }

    public function withServiceClass(string $class): self
    {
        $this->container->set(
            $class,
            static function () use ($class) {
                return new $class();
            }
        );

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function withServiceClassAutowired(string $class): self
    {
        if (!class_exists($class)) {
            throw new ReflectionException("$class does not exist");
        }

        $parameters = [];
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (null !== $constructor) {
            $reflectionParameters = $constructor->getParameters();

            foreach ($reflectionParameters as $reflectionParameter) {
                if ($reflectionParameter->isDefaultValueAvailable()) {
                    $parameters[$reflectionParameter->getPosition()] = $reflectionParameter->getDefaultValue();
                }

                $type = $reflectionParameter->getType();

                if ($type instanceof ReflectionNamedType) {
                    if ($this->container->has($type->getName())) {
                        $parameters[$reflectionParameter->getPosition()] = $this->container->get($type->getName());
                    } else {
                        $parameters[$reflectionParameter->getPosition()] = $type;
                    }
                }
            }
        }

        ksort($parameters);

        $this->container->set(
            $class,
            static function (ContainerInterface $container) use ($class, $parameters) {
                $finalParams = [];
                foreach ($parameters as $parameter) {
                    if ($parameter instanceof ReflectionNamedType) {
                        $finalParams[] = $container->get($parameter->getName());
                    } else {
                        $finalParams[] = $parameter;
                    }
                }

                return new $class(...$finalParams);
            }
        );

        return $this;
    }

    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewareDispatcher->addMiddleware($middleware);

        return $this;
    }

    public function withMiddlewareFromContainer(string $id): self
    {
        $this->middlewareDispatcher->addMiddleware($this->container->get($id));

        return $this;
    }
}
