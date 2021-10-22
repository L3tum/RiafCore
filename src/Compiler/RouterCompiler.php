<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
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
     * @var array<string, array<string, mixed>>
     */
    private array $routingTree = [];

    /**
     * @var StaticRoute[]
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
        $this->openResultFile($config->getRouterFilepath());
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

        $this->generateRouter($this->staticRoutes, $this->routingTree, $config->getRouterNamespace());

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

    private function analyzeRoute(Route $route, string $targetClass, string $targetMethod): void
    {
        if (!str_contains($route->getRoute(), '{')) {
            if (isset($this->staticRoutes[$route->getRoute()])) {
                throw new RuntimeException('Duplicated Route ' . $route->getRoute());
            }
            $this->staticRoutes[$route->getRoute()] = new StaticRoute($route->getRoute(), $route->getMethod(), $targetClass, $targetMethod);

            return;
        }

        if (!isset($this->routingTree[$route->getMethod()])) {
            $this->routingTree[$route->getMethod()] = [];
        }

        $currentRouting = &$this->routingTree[$route->getMethod()];

        $routingParts = explode('/', $route->getRoute());
        $lastRoute = array_key_last($routingParts);

        foreach ($routingParts as $key => $part) {
            $parameter = null;
            $requirement = null;
            if (str_starts_with($part, '{')) {
                $parameter = trim($part, '{}');
                $requirement = $route->getRequirement($parameter);
                $part = "\{$parameter=$requirement}";
            }

            if (!isset($currentRouting[$part])) {
                $currentRouting[$part] = ['index' => $key];

                if ($parameter !== null) {
                    $currentRouting[$part]['parameter'] = $parameter;
                    $currentRouting[$part]['pattern'] = $requirement;
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
     * @param StaticRoute[]                       $staticRoutes
     * @param array<string, array<string, mixed>> $routingTree
     */
    private function generateRouter(array $staticRoutes, array $routingTree, string $namespace): void
    {
        $compiler = $this;
        ob_start();
        require dirname(__DIR__, 2) . '/templates/Router.php';
        $this->writeLine(ob_get_clean() ?: '');
    }

    /**
     * @internal
     *
     * @param array<string, true> $capturedParams
     *
     * @return string[]
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
                    $params[] = "$parameter->name: ($type)\$capturedParams[\"$parameter->name\"]";
                }
                // Type is not named (therefore there may be no type-hint at all) or string,
                // so we do not need to care about it.
                else {
                    $params[] = "$parameter->name: \$capturedParams[\"$parameter->name\"]";
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
}
