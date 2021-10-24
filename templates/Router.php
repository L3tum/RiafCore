<?php declare(strict_types=1);

/** @var string $namespace */
/** @var array<string, array<string, StaticRoute>> $staticRoutes */
/** @var array<string, array<string, mixed>> $routingTree */
/** @var RouterCompiler $compiler */

use Riaf\Compiler\Router\StaticRoute;
use Riaf\Compiler\RouterCompiler;

$newLine = PHP_EOL;

if (!function_exists('includeLeaf')) {
    function includeLeaf(string $uri, array $route, int $indentation, bool $firstRoute, array $capturedParams, RouterCompiler $compiler, bool $generateCall = true): void
    {
        include __DIR__ . '/RouteLeaf.php';
    }
}

if (!function_exists('writeLine')) {
    function writeLine(string $line, ?int $indentation = null): void
    {
        $indentation = $indentation ?? 0;
        $newLine = PHP_EOL;
        $indents = implode('', array_fill(0, $indentation, "\t"));
        echo "$indents$line$newLine";
    }
}

/**
 * @psalm-suppress TypeDoesNotContainType This is a safety check...
 */
if (!is_array($staticRoutes) || !is_array($routingTree) || !is_string($namespace) || !$compiler instanceof RouterCompiler) {
    return;
}

writeLine('<?php');

?>

namespace <?php echo $namespace; ?>;

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
    public function __construct(private ContainerInterface $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handle($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
<?php
    if (count($staticRoutes) > 0) {
        writeLine('return match ($method)', 2);
        writeLine('{', 2);
        foreach ($staticRoutes as $method => $routeCollection) {
            writeLine("\"$method\" => match(\$path) {", 3);

            foreach ($routeCollection as $route => $staticRoute) {
                $targetClass = $staticRoute->getTargetClass();
                $targetMethod = $staticRoute->getTargetMethod();
                /**
                 * @psalm-suppress InternalMethod
                 * @phpstan-ignore-next-line
                 */
                $params = implode(', ', $compiler->generateParams($targetClass, $targetMethod));
                writeLine(
                    "\"$route\" => \$this->container->get(\"{$targetClass}\")->{$targetMethod}({$params}),",
                    4
                );
            }

            if (count($routingTree) > 0) {
                writeLine('default => $this->handleDynamicRoute($method, $path, $request)', 4);
            } else {
                writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 4);
            }
            writeLine('},', 3);
        }
        if (count($routingTree) > 0) {
            writeLine('default => $this->handleDynamicRoute($method, $path, $request)', 3);
        } else {
            writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 3);
        }
        writeLine('};', 2);
    } elseif (count($routingTree) > 0) {
        writeLine('return $this->handleDynamicRoute($method, $path, $request);', 2);
    } else {
        writeLine('return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);', 2);
    }
?>
    }

    private function handleDynamicRoute(string $method, string $path, ServerRequestInterface $request): ?ResponseInterface
    {
        $uriParts = explode('/', $path);
        $countParts = count($uriParts);
        /** @var array<string, string> $capturedParams */
        $capturedParams = [];

<?php
        $firstOne = true;
        foreach ($routingTree as $method => $routes) {
            $check = ($firstOne ? 'if' : 'elseif');
            writeLine("{$check}(\$method === \"$method\")", 2);
            writeLine('{', 2);

            $firstRoute = true;
            foreach ($routes as $uri => $route) {
                includeLeaf($uri, $route, 3, $firstRoute, [], $compiler, true);
                $firstRoute = false;
            }
            writeLine('}', 2);

            $firstOne = false;
        }
?>
        return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);
    }

    public function matchRoute(string $method, string $path): ?string
    {
<?php
    if (count($staticRoutes) > 0) {
        writeLine('return match ($method)', 2);
        writeLine('{', 2);
        foreach ($staticRoutes as $method => $routeCollection) {
            writeLine("\"$method\" => match(\$path) {", 3);

            foreach ($routeCollection as $route => $staticRoute) {
                $targetClass = $staticRoute->getTargetClass();
                $targetMethod = $staticRoute->getTargetMethod();
                writeLine(
                    "\"$route\" => \"$targetClass::$targetMethod\",",
                    4
                );
            }

            if (count($routingTree) > 0) {
                writeLine('default => $this->matchDynamicRoute($method, $path)', 4);
            } else {
                writeLine('default => null', 4);
            }
            writeLine('},', 3);
        }
        if (count($routingTree) > 0) {
            writeLine('default => $this->matchDynamicRoute($method, $path)', 3);
        } else {
            writeLine('default => null', 3);
        }
        writeLine('};', 2);
    } elseif (count($routingTree) > 0) {
        writeLine('return $this->matchDynamicRoute($method, $path);', 2);
    } else {
        writeLine('return null;', 2);
    }
?>
    }

    private function matchDynamicRoute(string $method, string $path): ?string
    {
        $uriParts = explode('/', $path);
        $countParts = count($uriParts);

<?php
    $firstOne = true;
    foreach ($routingTree as $method => $routes) {
        $check = ($firstOne ? 'if' : 'elseif');
        writeLine("{$check}(\$method === \"$method\")", 2);
        writeLine('{', 2);

        $firstRoute = true;
        foreach ($routes as $uri => $route) {
            includeLeaf($uri, $route, 3, $firstRoute, [], $compiler, false);
            $firstRoute = false;
        }
        writeLine('}', 2);

        $firstOne = false;
    }
?>
        return null;
    }
}
