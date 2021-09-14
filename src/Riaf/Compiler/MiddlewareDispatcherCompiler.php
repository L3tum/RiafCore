<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ReflectionClass;
use Riaf\Compiler\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\PsrExtensions\Middleware\Middleware;

class MiddlewareDispatcherCompiler extends BaseCompiler
{
    public function compile(): bool
    {
        /** @var MiddlewareDispatcherCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot());
        $middlewares = [];

        foreach ($classes as $class) {
            /** @var ReflectionClass<object> $class */
            $attributes = $class->getAttributes(Middleware::class);

            if (count($attributes) === 0) {
                continue;
            }

            $attribute = $attributes[0];
            /** @var Middleware $instance */
            $instance = $attribute->newInstance();
            $middlewares[] = [$instance->getPriority(), $class];
        }

        usort($middlewares, static function (array $a, array $b) {
            if ($a[0] > $b[0]) {
                return -1;
            }

            if ($a[0] < $b[0]) {
                return 1;
            }

            return 0;
        });

        $handle = $this->openResultFile($config->getMiddlewareDispatcherFilepath());

        $this->generateMiddlewareDispatcher($handle, $config->getMiddlewareDispatcherNamespace(), $middlewares);

        return true;
    }

    /**
     * @param resource                                              $handle
     * @param array<int, array{0: int, 1: ReflectionClass<object>}> $middlewares
     */
    private function generateMiddlewareDispatcher(&$handle, string $namespace, array $middlewares): void
    {
        $this->writeLine($handle, '<?php');
        $this->writeLine(
            $handle,
            <<<HEADER
namespace $namespace;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class MiddlewareDispatcher implements RequestHandlerInterface
{
    private ?ResponseInterface \$response;
    private int \$currentMiddleware = -1;
    private const MIDDLEWARES = [
HEADER
        );

        foreach ($middlewares as $middleware) {
            /** @var ReflectionClass<object> $class */
            $class = $middleware[1];
            $this->writeLine($handle, "\"$class->name\",", 2);
        }

        $this->writeLine(
            $handle,
            <<<ENDING
    ];
    
    public function __construct(private ContainerInterface \$container)
    {
    }
    
    public function handle(ServerRequestInterface \$request): ResponseInterface
    {
        \$this->currentMiddleware++;
        \$middleware = self::MIDDLEWARES[\$this->currentMiddleware] ?? null;
        
        if (\$middleware === null)
        {
            if (\$this->response === null)
            {
                \$this->response = \$this->container->get(ResponseFactoryInterface::class)->createResponse(500);
            }
            
            \$this->currentMiddleware = -1;
            
            return \$this->response;
        }
        
        /** @var MiddlewareInterface \$middlewareInstance */
        \$middlewareInstance = \$this->container->get(\$middleware);
        
        return \$middlewareInstance->process(\$request, \$this);
    }
}
ENDING
        );
    }

    public function supportsCompilation(): bool
    {
        return $this->config instanceof MiddlewareDispatcherCompilerConfiguration;
    }
}
