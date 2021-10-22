<?php declare(strict_types=1);

/** @var string $namespace */
/** @var array<string, StaticRoute> $staticRoutes */
/** @var array<string, array<string, mixed>> $routingTree */
/** @var RouterCompiler $compiler */

use Riaf\Compiler\Router\StaticRoute;
use Riaf\Compiler\RouterCompiler;

if (!function_exists('includeLeaf')) {
    function includeLeaf(string $uri, array $route, int $indentation, bool $firstRoute, array $capturedParams, RouterCompiler $compiler): void
    {
        include __DIR__ . '/RouteLeaf.php';
    }
}

if (!function_exists('writeLine')) {
    function writeLine(string $line, ?int $indentation = null): void
    {
        $indentation = $indentation ?? 0;
        echo sprintf('%s%s%s', implode('', array_fill(0, $indentation, "\t")), $line, PHP_EOL);
    }
}

echo '<?php' . PHP_EOL;

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
        $combined = sprintf("%s_%s", $request->getMethod(), $request->getUri()->getPath());
        return match ($combined)
        {
<?php
    foreach ($staticRoutes as $route => $staticRoute) {
        /**
         * @psalm-suppress InternalMethod
         */
        echo sprintf(
            "\t\t\t\"%s_%s\" => \$this->container->get(\"%s\")->%s(%s),%s",
            $staticRoute->getMethod(),
            $route,
            $staticRoute->getTargetClass(),
            $staticRoute->getTargetMethod(),
            /**
             * @phpstan-ignore-next-line
             */
            implode(', ', $compiler->generateParams($staticRoute->getTargetClass(), $staticRoute->getTargetMethod())),
            PHP_EOL
        );
    }
?>
            default => $this->handleDynamicRoute($request)
        };
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
        echo sprintf("\t\t%s (\$method === \"%s\")%s", $firstOne ? 'if' : 'elseif', $method, PHP_EOL);
        echo "\t\t{" . PHP_EOL;
        $firstRoute = true;

        foreach ($routes as $uri => $route) {
            $capturedParams = [];
            $indentation = 3;
            require __DIR__ . '/RouteLeaf.php';
            $firstRoute = false;
        }

        echo "\t\t}" . PHP_EOL;

        $firstOne = false;
    }
?>

        return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);
    }
}
