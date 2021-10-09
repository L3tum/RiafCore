<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Psr\Http\Server\MiddlewareInterface;
use ReflectionClass;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\PsrExtensions\Middleware\Middleware;

class MiddlewareDispatcherCompiler extends BaseCompiler
{
    // TODO: Write Tests
    public function compile(): bool
    {
        $this->timing->start(self::class);

        /** @var MiddlewareDispatcherCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getMiddlewareDispatcherFilepath());

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->outputFile]);
        /** @var MiddlewareDefinition[] $middlewares */
        $middlewares = [];

        foreach ($classes as $class) {
            $definition = $this->getMiddlewareDefinition($class);

            if ($definition !== null) {
                $middlewares[] = $definition;
            }
        }

        foreach ($config->getAdditionalMiddlewares() as $additionalMiddleware) {
            if (is_string($additionalMiddleware) && class_exists($additionalMiddleware)) {
                $class = new ReflectionClass($additionalMiddleware);
                $definition = $this->getMiddlewareDefinition($class);

                if ($definition !== null) {
                    $middlewares[] = $definition;
                }
            } elseif ($additionalMiddleware instanceof MiddlewareDefinition) {
                $middlewares[] = $additionalMiddleware;
            }
        }

        usort($middlewares, static function (MiddlewareDefinition $a, MiddlewareDefinition $b) {
            // Sorting array by decreasing priority
            return ($a->getPriority() <=> $b->getPriority()) * -1;
        });

        $this->generateMiddlewareDispatcher($config->getMiddlewareDispatcherNamespace(), $middlewares);

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $middleware
     */
    private function getMiddlewareDefinition(ReflectionClass $middleware): ?MiddlewareDefinition
    {
        if (!$middleware->implementsInterface(MiddlewareInterface::class)) {
            return null;
        }

        /** @var ReflectionClass<MiddlewareInterface> $middleware */
        $attributes = $middleware->getAttributes(Middleware::class);

        if (count($attributes) === 0) {
            return null;
        }

        $attribute = $attributes[0];
        /** @var Middleware $instance */
        $instance = $attribute->newInstance();

        return new MiddlewareDefinition($middleware->getName(), $instance->getPriority());
    }

    /**
     * @param MiddlewareDefinition[] $middlewares
     */
    private function generateMiddlewareDispatcher(string $namespace, array $middlewares): void
    {
        $this->writeLine('<?php');
        $this->writeLine(
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
            $class = $middleware->getReflectionClass();
            $this->writeLine("\"$class->name\",", 2);
        }

        $this->writeLine(
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
