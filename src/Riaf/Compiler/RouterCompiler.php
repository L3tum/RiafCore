<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Riaf\Compiler\Configuration\RouterCompilerConfiguration;
use Riaf\Routing\Route;

class RouterCompiler extends BaseCompiler
{
    /**
     * @var array<string, array>
     */
    private array $routingTree = [];

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
        if ($this->config instanceof RouterCompilerConfiguration) {
            /** @var RouterCompilerConfiguration $config */
            $config = $this->config;
            $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot());

            foreach ($classes as $class) {
                $this->analyzeClass($class);
            }

            $this->openResultFile($config->getRouterFilepath());
            $this->generateHeader();
            $this->generateRoutingTree();
            $this->generateEnding();
        }
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
        $instance = null;
        $attributes = $class->getAttributes(Route::class);

        if (count($attributes) > 0) {
            $attribute = $attributes[0];
            /** @var Route $instance */
            $instance = $attribute->newInstance();
        }

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            if (count($attributes) > 0) {
                /** @var Route $methodRoute */
                $methodRoute = $attributes[0]->newInstance();

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

    // TODO: Handle duplicate routes gracefully
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

            if (preg_match("/\{([a-zA-Z][a-zA-Z0-9]*)\}/", $part, $matches) === 1) {
                $parameter = $matches[1];
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
     */
    private function generateLeaf(string $key, array $values): void
    {
        $index = $values['index'];
        $count = $index + 1;
        // Parameter
        if (isset($values['requirement'])) {
            $requirement = $values['requirement'];
            $parameter = $requirement['parameter'];
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
                    if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
                        $defaultValue = $parameter->getDefaultValue();
                        if (is_string($defaultValue)) {
                            $defaultValue = "\"$defaultValue\"";
                        }
                        $params[] = "$parameter->name: $defaultValue";
                        continue;
                    }

                    $type = $this->getReflectionClassFromReflectionType($parameter->getType());

                    // Could not fetch ReflectionClass of Type
                    if ($type === null) {
                        // If Type isn't null, then it's a built-in type.
                        // So we need to cast the captured param into the appropriate type
                        // and hope that it's correct...
                        // Exception is string, since the URI is already a string.
                        if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() !== 'string') {
                            $type = $parameter->getType()->getName();
                            $params[] = "$parameter->name: ($type)\$capturedParams[\"$parameter->name\"]";
                        }
                        // Type is null (therefore there may be no type-hint at all) or string,
                        // so we do not need to care about it.
                        else {
                            $params[] = "$parameter->name: \$capturedParams[\"$parameter->name\"]";
                        }
                        continue;
                    }

                    if ($type->name === ServerRequestInterface::class) {
                        $params[] = "$parameter->name: \$request";
                        continue;
                    }

                    $params[] = "$parameter->name: \$capturedParams[\"$parameter->name\"] ?? \$this->container->has(\"$parameter->name\") ? \$this->container->get(\"$parameter->name\") : \$this->container->get(\"$type->name\")";
                }

                $parameter = implode(', ', $params);
                $this->writeLine("return \$this->container->get(\"$class\")->$method($parameter);", $index + 5);
                $this->writeLine('}', $index + 4);
            }
            // TODO: Exception
        } else {
            $next = $values['next'];

            foreach ($next as $matching => $value) {
                $this->generateLeaf($matching, $value);
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
