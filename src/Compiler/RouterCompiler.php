<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Riaf\Compiler\Emitter\RouterEmitter;
use Riaf\Compiler\Router\StaticRoute;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\RouterCompilerConfiguration;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Routing\Route;
use RuntimeException;
use Throwable;

class RouterCompiler extends BaseCompiler
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $routingTree = [];

    /**
     * @var array<string, array<string, StaticRoute>>
     */
    private array $staticRoutes = [];

    /**
     * @var array<string, bool>
     */
    private array $recordedClasses = [];

    public function supportsCompilation(): bool
    {
        return $this->config instanceof RouterCompilerConfiguration;
    }

    /**
     * @throws Exception
     */
    public function compile(): bool
    {
        $this->timing->start(self::class);

        /** @var RouterCompilerConfiguration $config */
        $config = $this->config;
        $emitter = new RouterEmitter($this->config, $this);
        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->outputFile]);

        foreach ($classes as $class) {
            $this->analyzeClass($class);
        }

        foreach ($this->config->getAdditionalServices() as $definition) {
            if (is_string($definition)) {
                $routerClass = $definition;
            } elseif ($definition instanceof ServiceDefinition) {
                $routerClass = $definition->getClassName();
            } elseif ($definition instanceof MiddlewareDefinition) {
                try {
                    $routerClass = $definition->getReflectionClass()->name;
                } catch (Throwable) {
                    continue;
                }
            } else {
                continue;
            }

            if (class_exists($routerClass)) {
                $this->analyzeClass(new ReflectionClass($routerClass));
            }
        }

        $emitter->emitRouter($this->staticRoutes, $this->routingTree);

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws Exception
     */
    private function analyzeClass(ReflectionClass $class): void
    {
        if (isset($this->recordedClasses[$class->name])) {
            return;
        }
        $this->recordedClasses[$class->name] = true;

        $instance = null;
        $attributes = $class->getAttributes(Route::class);

        if (count($attributes) > 0) {
            $attribute = $attributes[0];
            /** @var Route $instance */
            $instance = $attribute->newInstance();
        }

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                /** @var Route $methodRoute */
                $methodRoute = $attribute->newInstance();

                if ($instance !== null) {
                    if ($instance->getMethod() !== $methodRoute->getMethod()) {
                        // TODO: Exception
                        throw new Exception();
                    }

                    $methodRoute->mergeRequirements($instance->getRequirements());
                    $methodRoute->mergeRoute($instance->getRoute());
                }

                $this->analyzeRoute($methodRoute, $class->getName(), $method->getName());
            }
        }
    }

    /**
     * @psalm-suppress PossiblyInvalidArrayOffset PHPStan gets it
     */
    private function analyzeRoute(Route $route, string $targetClass, string $targetMethod): void
    {
        if (!str_contains($route->getRoute(), '{')) {
            if (!isset($this->staticRoutes[$route->getMethod()])) {
                $this->staticRoutes[$route->getMethod()] = [];
            }
            if (isset($this->staticRoutes[$route->getMethod()][$route->getRoute()])) {
                throw new RuntimeException('Duplicated Route ' . $route->getRoute());
            }
            $this->staticRoutes[$route->getMethod()][$route->getRoute()] = new StaticRoute($route->getRoute(), $route->getMethod(), $targetClass, $targetMethod);

            return;
        }

        if (!isset($this->routingTree[$route->getMethod()])) {
            $this->routingTree[$route->getMethod()] = [];
        }

        $routingParts = explode('/', $route->getRoute());
        $lastRoute = array_key_last($routingParts);

        if (!isset($this->routingTree[$route->getMethod()][count($routingParts)])) {
            $this->routingTree[$route->getMethod()][count($routingParts)] = [];
        }

        /**
         * @var array<string, mixed> $currentRouting
         */
        $currentRouting = &$this->routingTree[$route->getMethod()][count($routingParts)];

        foreach ($routingParts as $key => $part) {
            $parameter = null;
            $requirement = null;

            if (str_starts_with($part, '{')) {
                $defaultKey = 'zzz_default_zzz';
                $parameter = trim($part, '{}');
                $requirement = $route->getRequirement($parameter);
                $part = "\{$parameter=$requirement}";

                if (!isset($currentRouting[$defaultKey])) {
                    $currentRouting[$defaultKey] = [];
                }

                $currentRouting = &$currentRouting[$defaultKey];
            }

            if (!isset($currentRouting[$part])) {
                $currentRouting[$part] = ['index' => $key];
            }

            if ($parameter !== null) {
                $currentRouting[$part]['parameter'] = $parameter;
                $currentRouting[$part]['pattern'] = $requirement;

                // Check if we should capture the parameter
                if (!isset($currentRouting[$part]['capture']) || $currentRouting[$part]['capture'] === false) {
                    $currentRouting[$part]['capture'] = false;
                    foreach ($this->getParameters($targetClass, $targetMethod) as $param) {
                        if ($param->name === $parameter) {
                            $currentRouting[$part]['capture'] = true;
                            break;
                        }
                    }
                }
            }

            if ($key === $lastRoute) {
                if (isset($currentRouting[$part]['call'])) {
                    throw new RuntimeException('Duplicated route ' . $route->getRoute());
                }

                $currentRouting[$part]['call'] = ['class' => $targetClass, 'method' => $targetMethod];
            } else {
                if (!isset($currentRouting[$part]['next'])) {
                    $currentRouting[$part]['next'] = [];
                }
                $currentRouting = &$currentRouting[$part]['next'];
            }
        }
    }

    /**
     * @return ReflectionParameter[]
     */
    private function getParameters(string $class, string $method): array
    {
        if (class_exists($class)) {
            $reflectionClass = new ReflectionClass($class);

            if ($reflectionClass->hasMethod($method)) {
                $reflectionMethod = $reflectionClass->getMethod($method);

                return $reflectionMethod->getParameters();
            }
        }

        return [];
    }

    /**
     * @param array<string, array<string, StaticRoute>> $staticRoutes
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     */
    private function generateRouter(array $staticRoutes, array $routingTree, string $namespace): void
    {
        $compiler = $this;
        ob_start();
        require dirname(__DIR__, 2) . '/templates/Router.php';
        $this->writeLine(ob_get_clean() ?: '');
    }

    /**
     * Adds the specified route to the routing table
     *
     * @param Route $route
     * @param string $handler
     * @return $this
     */
    public function addRoute(Route $route, string $handler): self
    {
        if (str_contains($handler, '::')) {
            [$targetClass, $targetMethod] = explode('::', $handler, 2);
        } else {
            $targetClass = $handler;
            $targetMethod = '';
        }

        $this->analyzeRoute($route, $targetClass, $targetMethod);

        return $this;
    }

    /**
     * @param array<string, string> $capturedParams
     *
     * @return string[]
     *
     * @internal
     */
    public function generateParams(string $class, string $method, array $capturedParams = []): array
    {
        $params = [];

        foreach ($this->getParameters($class, $method) as $parameter) {
            // Has captured the parameter
            if (isset($capturedParams[$parameter->name])) {
                // If Type isn't null, then it's a built-in type.
                // So we need to cast the captured param into the appropriate type
                // and hope that it's correct...
                // Exception is string, since the URI is already a string.
                if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() !== 'string') {
                    $type = $parameter->getType()->getName();
                    $params[] = "$parameter->name: ($type){$capturedParams[$parameter->name]}";
                }
                // Type is not named (therefore there may be no type-hint at all) or string,
                // so we do not need to care about it.
                else {
                    $params[] = "$parameter->name: {$capturedParams[$parameter->name]}";
                }
                continue;
            }

            $type = $this->getReflectionClassFromReflectionType($parameter->getType());

            if ($type !== null && $type->name === ServerRequestInterface::class) {
                // Param is the Request itself
                $params[] = "$parameter->name: \$request";
                continue;
            }

            $param = "$parameter->name: ";

            if ($type !== null) {
                $param .= "\$this->container->has(\"$type->name\") ? \$this->container->get(\"$type->name\") : ";
            }

            $defaultValue = null;

            if ($parameter->isDefaultValueAvailable()) {
                $defaultValue = $parameter->getDefaultValue();
                if (!is_object($defaultValue) && !is_array($defaultValue)) {
                    if (is_string($defaultValue)) {
                        $defaultValue = "\"$defaultValue\"";
                    } elseif (null === $defaultValue) {
                        $defaultValue = 'null';
                    } elseif (is_bool($defaultValue)) {
                        $defaultValue = $defaultValue ? 'true' : 'false';
                    }
                } else {
                    $defaultValue = null;
                }
            }

            if ($defaultValue === null) {
                $param .= "\$this->container->get(\"$parameter->name\")";
            } else {
                $param .= "\$this->container->has(\"$parameter->name\") ? \$this->container->get(\"$parameter->name\") : $defaultValue";
            }

            $params[] = $param;
        }

        return $params;
    }
}
