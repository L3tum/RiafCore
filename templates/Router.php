<?php declare(strict_types=1);

/** @var string $namespace */
/** @var array<string, StaticRoute> $staticRoutes */
/** @var array<string, array<string, mixed>> $routingTree */
/** @var \Riaf\Compiler\RouterCompiler $compiler */

use Riaf\Compiler\Router\StaticRoute;

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
        return $this->resolveStaticRoute($request)
            ?? $this->resolveDynamicRoute($request)
            ?? $this->container->get(ResponseFactoryInterface::class)->createResponse(404);
    }

    private function resolveStaticRoute(ServerRequestInterface $request): ?ResponseInterface
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
            default => null
        };
    }

    private function resolveDynamicRoute(ServerRequestInterface $request): ?ResponseInterface
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

        return null;
    }
}
