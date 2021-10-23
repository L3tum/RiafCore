<?php declare(strict_types=1);

/** @var string $namespace */
/** @var array<string, StaticRoute> $staticRoutes */
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
<?php
    if (count($staticRoutes) > 0) {
        writeLine('$combined = "{$request->getMethod()}_{$request->getUri()->getPath()}";', 2);
        writeLine('return match ($combined)', 2);
        writeLine('{', 2);
        foreach ($staticRoutes as $route => $staticRoute) {
            $method = $staticRoute->getMethod();
            $targetClass = $staticRoute->getTargetClass();
            $targetMethod = $staticRoute->getTargetMethod();
            /**
             * @psalm-suppress InternalMethod
             * @phpstan-ignore-next-line
             */
            $params = implode(', ', $compiler->generateParams($targetClass, $targetMethod));
            writeLine(
                "\"{$method}_{$route}\" => \$this->container->get(\"{$targetClass}\")->{$targetMethod}({$params}),",
                3
            );
        }
        writeLine('default => $this->handleDynamicRoute($request)', 3);
        writeLine('};', 2);
    } elseif (count($routingTree) > 0) {
        writeLine('return $this->handleDynamicRoute($request);', 2);
    } else {
        writeLine('return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);', 2);
    }
?>
    }

    private function handleDynamicRoute(ServerRequestInterface $request): ?ResponseInterface
    {
        $uriParts = explode('/', $request->getUri()->getPath());
        $countParts = count($uriParts);
        $method = $request->getMethod();
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

    public function matchRoute(ServerRequestInterface $request): ?string
    {
<?php
    if (count($staticRoutes) > 0) {
        writeLine('$combined = "{$request->getMethod()}_{$request->getUri()->getPath()}";', 2);
        writeLine('return match ($combined)', 2);
        writeLine('{', 2);
        foreach ($staticRoutes as $route => $staticRoute) {
            $method = $staticRoute->getMethod();
            $targetClass = $staticRoute->getTargetClass();
            $targetMethod = $staticRoute->getTargetMethod();
            /**
             * @psalm-suppress InternalMethod
             * @phpstan-ignore-next-line
             */
            writeLine(
                "\"{$method}_{$route}\" => \"{$targetClass}::{$targetMethod}\",",
                3
            );
        }
        writeLine('default => $this->matchDynamicRoute($request)', 3);
        writeLine('};', 2);
    } elseif (count($routingTree) > 0) {
        writeLine('return $this->matchDynamicRoute($request);', 2);
    } else {
        writeLine('return null;', 2);
    }
?>
    }

    private function matchDynamicRoute(ServerRequestInterface $request): ?string
    {
        $uriParts = explode('/', $request->getUri()->getPath());
        $countParts = count($uriParts);
        $method = $request->getMethod();

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
