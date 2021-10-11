<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\RouterCompilerConfiguration;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Routing\Route;
use RuntimeException;
use Throwable;

class RouterCompiler extends BaseCompiler
{
    /**
     * @var array<string, array>
     */
    private array $routingTree = [];

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

        $this->generateHeader();
        $this->generateRoutingTree();
        $this->generateEnding();

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
        if (!isset($this->routingTree[$route->getMethod()])) {
            $this->routingTree[$route->getMethod()] = [];
        }

        $currentRouting = &$this->routingTree[$route->getMethod()];

        $routingParts = explode('/', $route->getRoute());
        $lastRoute = array_key_last($routingParts);

        foreach ($routingParts as $key => $part) {
            if (!isset($currentRouting[$part])) {
                $currentRouting[$part] = ['index' => $key];
            }

            if (str_starts_with($part, '{')) {
                $parameter = trim($part, '{}');
                $requirement = $route->getRequirement($parameter);
                $currentRouting[$part]['requirement'] = ['parameter' => $parameter, 'pattern' => $requirement];
            }

            if ($key === $lastRoute) {
                $currentRouting[$part]['call'] = ['class' => $targetClass, 'method' => $targetMethod];
            } else {
                if (!isset($currentRouting[$part]['next'])) {
                    $currentRouting[$part]['next'] = [];
                }

                $currentRouting = &$currentRouting[$part]['next'];
            }
        }
    }

    private function generateHeader(): void
    {
        if ($this->config instanceof RouterCompilerConfiguration) {
            /** @var RouterCompilerConfiguration $config */
            $config = $this->config;
            $this->writeLine('<?php');

            $namespace = $config->getRouterNamespace();
            $this->writeLine(
                <<<HEADER
namespace $namespace;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Riaf\PsrExtensions\Middleware\Middleware;

#[Middleware(-100)]
class Router implements MiddlewareInterface, RequestHandlerInterface
{
    public function __construct(private ContainerInterface \$container)
    {
    }
    
    public function process(ServerRequestInterface \$request, RequestHandlerInterface \$handler): ResponseInterface
    {
        return \$this->handle(\$request);
    }
    
    public function handle(ServerRequestInterface \$request): ResponseInterface
    {
        \$capturedParams = [];
HEADER
            );
        }
    }

    private function generateRoutingTree(): void
    {
        if (count($this->routingTree) > 0) {
            $this->writeLine('$uriParts = explode("/", $request->getUri()->getPath());', 2);
        }

        foreach ($this->routingTree as $key => $tree) {
            $this->writeLine("if(\$request->getMethod() === \"$key\")", 2);
            $this->writeLine('{', 2);
            $currentTree = $this->routingTree[$key];

            foreach ($currentTree as $matching => $next) {
                $this->generateLeaf((string) $matching, $next);
            }

            $this->writeLine('}', 2);
        }
    }

    /**
     * @param array{index: int, requirement: array<string, string|null>, call: array<string, string>} $values
     * @param array<string, bool>                                                                     $capturedParams
     */
    private function generateLeaf(string $key, array $values, array $capturedParams = []): void
    {
        $index = $values['index'];
        $count = $index + 1;
        // Parameter
        if (isset($values['requirement'])) {
            $requirement = $values['requirement'];
            $parameter = (string) $requirement['parameter'];
            $pattern = $requirement['pattern'];

            if ($pattern !== null) { // Parameter with Requirement
                $this->writeLine("if(preg_match(\"/^$pattern$/\", \$uriParts[$index], \$matches) === 1)", $index + 3);
                $this->writeLine('{', $index + 3);
                $this->writeLine("\$capturedParams[\"$parameter\"] = \$matches[0];", $index + 4);
            } else { // Parameter without requirement
                $this->writeLine("if(count(\$uriParts) >= $count)");
                $this->writeLine('{', $index + 3);
                $this->writeLine("\$capturedParams[\"$parameter\"] = \$uriParts[$index];", $index + 4);
            }

            $capturedParams[$parameter] = true;
        } // Normal route
        else {
            $this->writeLine("if(\$uriParts[$index] === \"$key\")", $index + 3);
            $this->writeLine('{', $index + 3);
        }

        if (!isset($values['next'])) {
            if (isset($values['call'])) {
                $class = $values['call']['class'];
                $method = $values['call']['method'];

                $this->writeLine("if(count(\$uriParts) === $count)", $index + 4);
                $this->writeLine('{', $index + 4);

                $params = [];

                foreach ($this->getParameters($class, $method) as $parameter) {
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
                            $params[] = "$parameter->name: $defaultValue";
                            continue;
                        }
                    }

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

                    if ($type !== null) {
                        // Param is the Request itself
                        if ($type->name === ServerRequestInterface::class) {
                            $params[] = "$parameter->name: \$request";
                        } // Param is another type (which may be in the Container)
                        else {
                            $params[] = "$parameter->name: \$this->container->has(\"$parameter->name\") ? \$this->container->get(\"$parameter->name\") : \$this->container->get(\"$type->name\")";
                        }
                    } else {
                        // Could not find Type, last chance is the name of the parameter
                        $params[] = "$parameter->name: \$this->container->get(\"$parameter->name\")";
                    }
                }

                $parameter = implode(', ', $params);
                $this->writeLine("return \$this->container->get(\"$class\")->$method($parameter);", $index + 5);
                $this->writeLine('}', $index + 4);
            } else {
                // TODO: Exception
                throw new RuntimeException('Invalid Routing Configuration');
            }
        } else {
            $next = $values['next'];

            foreach ($next as $matching => $value) {
                $this->generateLeaf($matching, $value, $capturedParams);
            }
        }

        $this->writeLine('}', $index + 3);
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

    public function generateEnding(): void
    {
        $this->writeLine('return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);', 2);
        $this->writeLine('}', 1);
        $this->writeLine('}');
    }
}
