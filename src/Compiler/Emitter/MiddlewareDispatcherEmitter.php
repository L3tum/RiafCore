<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;

class MiddlewareDispatcherEmitter extends BaseEmitter
{
    /**
     * @param MiddlewareDefinition[] $middlewares
     */
    public function emitMiddlewareDispatcher(array &$middlewares): void
    {
        /** @var MiddlewareDispatcherCompilerConfiguration $config */
        $config = $this->config;
        $namespace = $config->getMiddlewareDispatcherNamespace();
        $this->openResultFile($config->getMiddlewareDispatcherFilepath());
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
    private ?ResponseInterface \$response = null;
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
}
