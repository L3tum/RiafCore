<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use JetBrains\PhpStorm\Pure;
use Riaf\Compiler\Router\StaticRoute;
use Riaf\Compiler\RouterCompiler;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\RouterCompilerConfiguration;

class RouterEmitter extends BaseEmitter
{
    #[Pure]
    public function __construct(BaseConfiguration $config, RouterCompiler $compiler)
    {
        parent::__construct($config, $compiler);
    }

    /**
     * @param array<string, array<string, StaticRoute>>       $staticRoutes
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     *
     * @throws Exception
     */
    public function emitRouter(array &$staticRoutes, array &$routingTree): void
    {
        /** @var RouterCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getRouterFilepath());

        $this->emitHeader($config->getRouterNamespace());

        $this->emitStaticHandler($staticRoutes, $routingTree);
        $this->emitStaticMatcher($staticRoutes, $routingTree);

        if (count($routingTree) > 1 || count($routingTree['HEAD']) > 0) {
            $this->emitDynamicHandler($routingTree, $staticRoutes);
            $this->emitDynamicMatcher($routingTree, $staticRoutes);
        }

        $this->emitEnding();
    }

    private function emitHeader(string $namespace): void
    {
        $this->writeLine('<?php');
        $this->writeLine("namespace $namespace;");
        $this->writeLine(
            <<<HEADER
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

HEADER
        );
    }

    /**
     * @param array<string, array<string, StaticRoute>>       $staticRoutes
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     */
    private function emitStaticHandler(array &$staticRoutes, array &$routingTree): void
    {
        $this->writeLine('public function handle(ServerRequestInterface $request): ResponseInterface', 1);
        $this->writeLine('{', 1);
        $this->writeLine('$method = $request->getMethod();', 2);
        $this->writeLine('$path = $request->getUri()->getPath();', 2);

        if (count($routingTree) > 1 || count($routingTree['HEAD']) > 0) {
            $default = '$this->handleDynamicRoute($method, $path, $request)';
        } else {
            $default = '$this->container->get(ResponseFactoryInterface::class)->createResponse(404)';
        }

        if (count($staticRoutes) === 0) {
            $this->writeLine("return $default;", 2);
        } else {
            $this->writeLine('return match($method)', 2);
            $this->writeLine('{', 2);

            foreach ($staticRoutes as $method => $routeCollection) {
                $this->writeLine("\"$method\" => match(\$path) {", 3);

                foreach ($routeCollection as $route => $staticRoute) {
                    $targetClass = $staticRoute->getTargetClass();
                    $targetMethod = $staticRoute->getTargetMethod();
                    /**
                     * @noinspection PhpPossiblePolymorphicInvocationInspection
                     * @psalm-suppress UndefinedMethod
                     * @phpstan-ignore-next-line
                     */
                    $params = implode(', ', $this->compiler->generateParams($targetClass, $targetMethod));

                    if ($staticRoute->isStatic()) {
                        $this->writeLine("\"$route\" => \\$targetClass::$targetMethod($params),", 4);
                    } else {
                        $this->writeLine(
                            "\"$route\" => \$this->container->get(\"{$targetClass}\")->{$targetMethod}({$params}),",
                            4
                        );
                    }
                }

                if (
                    $method === 'HEAD'
                    && !$this->hasRoutesWithMethod($routingTree, 'HEAD')
                    && $this->hasRoutesWithMethod($staticRoutes, 'GET')
                ) {
                    // Re-run the match with GET
                    $this->writeLine('default => $this->handle($request->withMethod("GET"))', 4);
                } elseif ($this->hasRoutesWithMethod($routingTree, $method)) {
                    $this->writeLine('default => $this->handleDynamicRoute($method, $path, $request)', 4);
                } else {
                    $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 4);
                }
                $this->writeLine('},', 3);
            }

            $this->writeLine("default => $default", 3);
            $this->writeLine('};', 2);
        }

        $this->writeLine('}', 1);
    }

    /**
     * @param array<string, array<mixed>> $tree
     * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
     */
    private function hasRoutesWithMethod(array &$tree, string $method): bool
    {
        return isset($tree[$method]) && count($tree[$method]) > 0;
    }

    /**
     * @param array<string, array<string, StaticRoute>>       $staticRoutes
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     */
    private function emitStaticMatcher(array &$staticRoutes, array &$routingTree): void
    {
        $this->writeLine('public function match(string $method, string $path): ?string', 1);
        $this->writeLine('{', 1);

        if (count($routingTree) > 1 || count($routingTree['HEAD']) > 0) {
            $default = '$this->matchDynamicRoute($method, $path)';
        } else {
            $default = 'null';
        }

        if (count($staticRoutes) === 0) {
            $this->writeLine("return $default;");
        } else {
            $this->writeLine('return match($method)', 2);
            $this->writeLine('{', 2);

            foreach ($staticRoutes as $method => $routeCollection) {
                $this->writeLine("\"$method\" => match(\$path) {", 3);

                foreach ($routeCollection as $route => $staticRoute) {
                    $targetClass = $staticRoute->getTargetClass();
                    $targetMethod = $staticRoute->getTargetMethod();
                    $this->writeLine(
                        "\"$route\" => \"{$targetClass}::{$targetMethod}\",",
                        4
                    );
                }

                if (
                    $method === 'HEAD'
                    && !$this->hasRoutesWithMethod($routingTree, 'HEAD')
                    && $this->hasRoutesWithMethod($staticRoutes, 'GET')
                ) {
                    // Re-run the match with GET
                    $this->writeLine('default => $this->match("GET", $path)', 4);
                } elseif ($this->hasRoutesWithMethod($routingTree, $method)) {
                    $this->writeLine('default => $this->matchDynamicRoute($method, $path)', 4);
                } else {
                    $this->writeLine('default => null', 4);
                }
                $this->writeLine('},', 3);
            }

            $this->writeLine("default => $default", 3);
            $this->writeLine('};', 2);
        }

        $this->writeLine('}', 1);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     * @param array<string, array<string, StaticRoute>>       $staticRoutes
     */
    private function emitDynamicHandler(array &$routingTree, array &$staticRoutes): void
    {
        $this->writeLine('private function handleDynamicRoute(string $method, string $path, ServerRequestInterface $request): ResponseInterface', 1);
        $this->writeLine('{', 1);
        $this->writeLine('$uriParts = explode("/", $path);', 2);
        $this->writeLine('$countParts = count($uriParts);', 2);
        $this->writeLine('return match($method)', 2);
        $this->writeLine('{', 2);

        foreach ($routingTree as $method => $counters) {
            $this->writeLine("\"$method\" => match(\$countParts)", 3);
            $this->writeLine('{', 3);

            foreach ($counters as $count => $routes) {
                $this->write("$count => ", 4);

                $lastUri = array_key_last($routes);
                $firstUri = array_key_first($routes);
                $hasGeneratedSubRoute = false;
                foreach ($routes as $uri => $route) {
                    $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
                    $this->walkRoutingTree(
                        $uri,
                        $route,
                        4,
                        $firstUri === $uri,
                        $lastUri === $uri,
                        [],
                        $hasGeneratedSubRouteTemp,
                        true
                    );
                    if ($hasGeneratedSubRouteTemp) {
                        $hasGeneratedSubRoute = true;
                    }
                }
            }

            if ($method === 'HEAD') {
                if ($this->hasRoutesWithMethod($staticRoutes, 'GET')) {
                    $this->writeLine('default => $this->handle($request->withMethod("GET"))', 4);
                } elseif ($this->hasRoutesWithMethod($routingTree, 'GET')) {
                    $this->writeLine('default => $this->handleDynamicRoute("GET", $path, $request->withMethod("GET"))', 4);
                } else {
                    $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 4);
                }
            } else {
                $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 4);
            }
            $this->writeLine('},', 3);
        }

        $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 3);
        $this->writeLine('};', 2);
        $this->writeLine('}', 1);
    }

    /**
     * @param array<string, mixed>  $route
     * @param array<string, string> $capturedParams
     */
    private function walkRoutingTree(
        string $uri,
        array $route,
        int $indentation,
        bool $firstRoute,
        bool $lastRoute,
        array $capturedParams,
        bool &$hasGeneratedRoute,
        bool $generateCall
    ): void {
        $index = $route['index'];

        if ($firstRoute) {
            if (!isset($route['pattern']) && !isset($route['parameter'])) {
                $this->writeLine("match(\$uriParts[$index])");
                $this->writeLine('{', $indentation);
                ++$indentation;
            } elseif (isset($route['pattern'])) {
                $this->writeLine('match(true)');
                $this->writeLine('{', $indentation);
                ++$indentation;
            }
        }

        // Parameter
        if (isset($route['parameter'])) {
            $parameter = (string) $route['parameter'];
            $pattern = $route['pattern'] ?? null;
            $capture = $route['capture'];

            if ($pattern !== null) { // Parameter with Requirement
                $this->write("preg_match(\"/^$pattern$/\", \$uriParts[$index], \$matches$index) === 1 => ", $indentation);
                if ($capture) {
                    $capturedParams[$parameter] = "\$matches{$index}[0]";
                }
                $hasGeneratedRoute = true;
            } elseif ($capture) { // Parameter without requirement
                $capturedParams[$parameter] = "\$uriParts[$index]";
            }
        } // Normal route
        else {
            $this->write("\"$uri\" => ", $indentation);
            $hasGeneratedRoute = true;
        }

        if (isset($route['call'])) {
            $class = $route['call']['class'];
            $method = $route['call']['method'];

            if ($generateCall) {
                /**
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 * @psalm-suppress UndefinedMethod
                 * @phpstan-ignore-next-line
                 */
                $params = implode(', ', $this->compiler->generateParams($class, $method, $capturedParams));

                if ($route['call']['static']) {
                    $this->writeLine("\\$class::$method($params),");
                } else {
                    $this->writeLine("\$this->container->get(\"$class\")->$method($params),");
                }
            } else {
                $this->writeLine("\"$class::$method\",");
            }
        }

        $needsDefaultArm = $lastRoute;

        if (isset($route['next'])) {
            $generatedLeaves = 0;
            $regexes = [];
            $firstUri = array_key_first($route['next']);
            $lastUri = array_key_last($route['next']);
            $hasGeneratedSubRoute = false;
            foreach ($route['next'] as $newUri => $newRoute) {
                $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
                if ($newUri === 'zzz_default_zzz') {
                    $regexes = $newRoute;
                    continue;
                }
                $this->walkRoutingTree(
                    $newUri,
                    $newRoute,
                    $indentation + 1,
                    $firstUri === $newUri,
                    $lastUri === $newUri,
                    $capturedParams,
                    $hasGeneratedSubRouteTemp,
                    $generateCall
                );
                ++$generatedLeaves;

                if ($hasGeneratedSubRouteTemp) {
                    $hasGeneratedSubRoute = true;
                }
            }

            if (count($regexes) > 0) {
                if ($generatedLeaves > 0) {
                    $this->write('default => ');
                    $needsDefaultArm = false;
                }

                $firstUri = array_key_first($regexes);
                $lastUri = array_key_last($regexes);
                $hasGeneratedSubRoute = false;
                foreach ($regexes as $newUri => $newRoute) {
                    $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
                    $this->walkRoutingTree(
                        $newUri,
                        $newRoute,
                        $indentation + 1,
                        $firstUri === $newUri,
                        $lastUri === $newUri,
                        $capturedParams,
                        $hasGeneratedSubRouteTemp,
                        $generateCall
                    );

                    if ($hasGeneratedSubRouteTemp) {
                        $hasGeneratedSubRoute = true;
                    }
                }
            } elseif ($generatedLeaves === 0) {
                $needsDefaultArm = false;
            }
        }

        if ($needsDefaultArm && $hasGeneratedRoute) {
            if ($generateCall) {
                $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404),', $indentation);
            } else {
                $this->writeLine('default => null,', $indentation);
            }
        }

        if ($lastRoute && $hasGeneratedRoute) {
            --$indentation;
            $this->writeLine('},', $indentation);
        }
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     * @param array<string, array<string, StaticRoute>>       $staticRoutes
     */
    private function emitDynamicMatcher(array &$routingTree, array &$staticRoutes): void
    {
        $this->writeLine('private function matchDynamicRoute(string $method, string $path): ?string', 1);
        $this->writeLine('{', 1);
        $this->writeLine('$uriParts = explode("/", $path);', 2);
        $this->writeLine('$countParts = count($uriParts);', 2);
        $this->writeLine('return match($method)', 2);
        $this->writeLine('{', 2);

        foreach ($routingTree as $method => $counters) {
            $this->writeLine("\"$method\" => match(\$countParts)", 3);
            $this->writeLine('{', 3);

            foreach ($counters as $count => $routes) {
                $this->write("$count => ", 4);

                $lastUri = array_key_last($routes);
                $firstUri = array_key_first($routes);
                $hasGeneratedSubRoute = false;
                foreach ($routes as $uri => $route) {
                    $hasGeneratedSubRouteTemp = $hasGeneratedSubRoute;
                    $this->walkRoutingTree(
                        $uri,
                        $route,
                        4,
                        $firstUri === $uri,
                        $lastUri === $uri,
                        [],
                        $hasGeneratedSubRouteTemp,
                        false
                    );
                    if ($hasGeneratedSubRouteTemp) {
                        $hasGeneratedSubRoute = true;
                    }
                }
            }

            if ($method === 'HEAD') {
                if ($this->hasRoutesWithMethod($staticRoutes, 'GET')) {
                    $this->writeLine('default => $this->match("GET", $path)', 4);
                } elseif ($this->hasRoutesWithMethod($routingTree, 'GET')) {
                    $this->writeLine('default => $this->matchDynamicRoute("GET", $path)', 4);
                } else {
                    $this->writeLine('default => null', 4);
                }
            } else {
                $this->writeLine('default => null', 4);
            }
            $this->writeLine('},', 3);
        }

        $this->writeLine('default => null', 3);
        $this->writeLine('};', 2);
        $this->writeLine('}', 1);
    }

    private function emitEnding(): void
    {
        $this->writeLine('}');
    }
}
