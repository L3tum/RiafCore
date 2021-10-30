<?php

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
     * @param array<string, array<string, StaticRoute>> $staticRoutes
     * @param array<string, array<int, array<string, mixed>>> $routingTree
     * @throws Exception
     */
    public function emitRouter(array $staticRoutes, array $routingTree): void
    {
        /** @var RouterCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getRouterFilepath());

        $this->emitHeader($config->getRouterNamespace());
        $this->emitStaticHandler($staticRoutes, count($routingTree) > 0);
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
     * @param array<string, array<string, StaticRoute>> $staticRoutes
     */
    private function emitStaticHandler(array $staticRoutes, bool $hasDynamicRoutes): void
    {
        $this->writeLine('public function handle(ServerRequestInterface $request): ResponseInterface', 1);
        $this->writeLine('{', 1);
        $this->writeLine('$method = $request->getMethod();', 2);
        $this->writeLine('$path = $request->getUri()->getPath();', 2);

        if (count($staticRoutes) === 0) {
            if ($hasDynamicRoutes) {
                $this->writeLine('return $this->handleDynamicRoute($method, $path, $request);', 2);
            } else {
                $this->writeLine('return $this->container->get(ResponseFactoryInterface::class)->createResponse(404);', 2);
            }
        } else {
            $this->writeLine('return match($method)', 2);
            $this->writeLine('{', 2);

            foreach ($staticRoutes as $method => $routeCollection) {
                $this->writeLine("\"$method\" => match(\$path) {", 3);

                foreach ($routeCollection as $route => $staticRoute) {
                    $targetClass = $staticRoute->getTargetClass();
                    $targetMethod = $staticRoute->getTargetMethod();

                    if ($targetMethod !== '') {
                        $params = implode(', ', $this->compiler->generateParams($targetClass, $targetMethod));
                        $this->writeLine(
                            "\"$route\" => \$this->container->get(\"{$targetClass}\")->{$targetMethod}({$params}),",
                            4
                        );
                    } else {
                        $this->writeLine(
                            "\"$route\" => \$this->container->get(ResponseFactoryInterface::class)->createResponse(404),",
                            4
                        );
                    }
                }

                if ($hasDynamicRoutes) {
                    $this->writeLine('default => $this->handleDynamicRoute($method, $path, $request)', 4);
                } else {
                    $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 4);
                }
                $this->writeLine('},', 3);
            }

            if ($hasDynamicRoutes) {
                $this->writeLine('default => $this->handleDynamicRoute($method, $path, $request)', 3);
            } else {
                $this->writeLine('default => $this->container->get(ResponseFactoryInterface::class)->createResponse(404)', 3);
            }
            $this->writeLine('};', 2);
        }

        $this->writeLine('}', 1);
    }

    private function emitEnding(): void
    {
        $this->writeLine('}');
    }
}
