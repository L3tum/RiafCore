<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Emitter\RouterEmitter;
use Riaf\Compiler\Router\StaticRoute;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\RouterCompilerConfiguration;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Metrics\Timing;
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

    private RouterEmitter $emitter;

    #[Pure]
    public function __construct(BaseConfiguration $config, ?AnalyzerInterface $analyzer = null, ?Timing $timing = null)
    {
        parent::__construct($config, $analyzer, $timing);
        $this->emitter = new RouterEmitter($config, $this);
    }

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
        $this->staticRoutes['HEAD'] = [];
        $this->routingTree['HEAD'] = [];

        /** @var RouterCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getRouterFilepath(), $this)]);

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

        // TODO: Run optimizations
        $this->emitter->emitRouter($this->staticRoutes, $this->routingTree);
        $this->staticRoutes = [];
        $this->routingTree = [];
        $this->recordedClasses = [];

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

        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
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

                if ($method->isStatic() && $class->isAnonymous()) {
                    // TODO: Exception
                    throw new Exception();
                }

                $this->analyzeRoute($methodRoute, $class->getName(), $method->getName(), $method->isStatic());
            }
        }
    }

    /**
     * @psalm-suppress PossiblyInvalidArrayOffset PHPStan gets it
     */
    private function analyzeRoute(Route $route, string $targetClass, string $targetMethod, bool $isStatic): void
    {
        $uri = $route->getRoute();
        $method = $route->getMethod();

        if (!str_contains($uri, '{')) {
            if (!isset($this->staticRoutes[$method])) {
                $this->staticRoutes[$method] = [];
            }
            if (isset($this->staticRoutes[$method][$uri])) {
                throw new RuntimeException('Duplicated Route ' . $uri);
            }
            $this->staticRoutes[$method][$uri] = new StaticRoute($uri, $method, $targetClass, $targetMethod, $isStatic);

            return;
        }

        if (!isset($this->routingTree[$method])) {
            $this->routingTree[$method] = [];
        }

        $routingParts = explode('/', $uri);
        $lastRoute = array_key_last($routingParts);

        if (!isset($this->routingTree[$method][count($routingParts)])) {
            $this->routingTree[$method][count($routingParts)] = [];
        }

        /**
         * @var array<string, mixed> $currentRouting
         */
        $currentRouting = &$this->routingTree[$method][count($routingParts)];

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
                    throw new RuntimeException('Duplicated route ' . $uri);
                }

                $currentRouting[$part]['call'] = ['class' => $targetClass, 'method' => $targetMethod, 'static' => $isStatic];
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
     * Adds the specified route to the routing table.
     *
     * @return $this
     *
     * @throws Exception
     */
    public function addRoute(Route $route, string $handler): self
    {
        if (str_contains($handler, '::')) {
            [$targetClass, $targetMethod] = explode('::', $handler, 2);
        } else {
            $targetClass = $handler;
            $targetMethod = $handler;
        }

        $isStatic = false;

        if (class_exists($targetClass)) {
            $class = new ReflectionClass($targetClass);

            if ($class->hasMethod($targetMethod)) {
                $method = $class->getMethod($targetMethod);

                if ($method->isStatic() && $class->isAnonymous()) {
                    // TODO: Exception
                    throw new Exception();
                }

                $isStatic = $method->isStatic();
            }
        }

        $this->analyzeRoute($route, $targetClass, $targetMethod, $isStatic);

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
