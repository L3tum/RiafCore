<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use ReflectionClass;
use Riaf\Compiler\Emitter\EventDispatcherEmitter;
use Riaf\Configuration\EventDispatcherCompilerConfiguration;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\ServiceDefinition;
use Riaf\PsrExtensions\EventDispatcher\Listener;
use RuntimeException;
use Throwable;

class EventDispatcherCompiler extends BaseCompiler
{
    /** @var array<string, array<array{class: string, method: string, static: bool}>> */
    private array $listeners = [];

    /** @var array<string, bool> */
    private array $recordedClasses = [];

    private ?EventDispatcherEmitter $emitter = null;

    /**
     * @throws Exception
     */
    public function compile(): bool
    {
        $this->timing->start(self::class);
        $this->emitter = new EventDispatcherEmitter($this->config, $this, $this->logger);
        /** @var EventDispatcherCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getEventDispatcherFilepath(), $this)]);

        foreach ($classes as $class) {
            $this->analyzeClass($class);
        }

        foreach ($this->config->getAdditionalServices() as $definition) {
            if ($definition instanceof ServiceDefinition) {
                $className = $definition->getClassName();
            } elseif ($definition instanceof MiddlewareDefinition) {
                try {
                    $className = $definition->getReflectionClass()->name;
                } catch (Throwable) {
                    continue;
                }
            } elseif (is_string($definition)) {
                $className = $definition;
            } else {
                continue;
            }

            if (!class_exists($className)) {
                throw new RuntimeException("Could not found additional listener $className");
            }

            $this->analyzeClass(new ReflectionClass($className));
        }

        $this->emitter->emitEventDispatcher($this->listeners);
        $this->listeners = [];
        $this->recordedClasses = [];

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function analyzeClass(ReflectionClass $class): void
    {
        if (isset($this->recordedClasses[$class->name])) {
            return;
        }
        $this->recordedClasses[$class->name] = true;

        /** @var ReflectionClass<object> $class */
        $attributes = $class->getAttributes(Listener::class);

        foreach ($attributes as $attribute) {
            /** @var Listener $listener */
            $listener = $attribute->newInstance();
            $event = $listener->getTarget();
            $method = $listener->getMethod();

            if (!class_exists($event)) {
                // TODO: Exception
                throw new RuntimeException("$event not found!");
            }

            if (!$class->hasMethod($method)) {
                $this->timing->stop(self::class);
                // TODO: Exception
                throw new RuntimeException("$method not found in $class->name");
            }

            $reflectionMethod = $class->getMethod($method);

            if ($reflectionMethod->isAbstract() || $reflectionMethod->isPrivate() || $reflectionMethod->isProtected() || $reflectionMethod->isClosure()) {
                $this->timing->stop(self::class);
                // TODO: Exception
                throw new RuntimeException("$method is not callable");
            }

            if (!isset($this->listeners[$event])) {
                $this->listeners[$event] = [];
            }

            $this->listeners[$event][] = ['class' => $class->name, 'method' => $method, 'static' => $reflectionMethod->isStatic()];
        }
    }

    /**
     * @throws Exception
     */
    public function addListener(string $event, string $handler): void
    {
        if (str_contains($handler, '::')) {
            [$class, $method] = explode('::', $handler, 2);
        } else {
            $class = $method = $handler;
        }

        if (!class_exists($class)) {
            // TODO: Exception
            throw new Exception("$class does not exist!");
        }
        $reflectionClass = new ReflectionClass($class);

        if (!$reflectionClass->hasMethod($method)) {
            throw new Exception("$class::$method does not exist!");
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = ['class' => $class, 'method' => $method, 'static' => $reflectionClass->getMethod($method)->isStatic()];
    }

    public function supportsCompilation(): bool
    {
        return $this->config instanceof EventDispatcherCompilerConfiguration;
    }
}
