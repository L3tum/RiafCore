<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionClass;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Emitter\MiddlewareDispatcherEmitter;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Configuration\MiddlewareDefinition;
use Riaf\Configuration\MiddlewareDispatcherCompilerConfiguration;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Metrics\Timing;
use Riaf\PsrExtensions\Middleware\Middleware;

class MiddlewareDispatcherCompiler extends BaseCompiler
{
    /** @var array<string, bool> */
    private array $recordedMiddlewares = [];

    private MiddlewareDispatcherEmitter $emitter;

    #[Pure]
    public function __construct(BaseConfiguration $config, ?AnalyzerInterface $analyzer = null, ?Timing $timing = null)
    {
        parent::__construct($config, $analyzer, $timing);
        $this->emitter = new MiddlewareDispatcherEmitter($config, $this);
    }

    public function compile(): bool
    {
        $this->timing->start(self::class);
        /** @var MiddlewareDispatcherCompilerConfiguration $config */
        $config = $this->config;

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->getOutputFile($config->getMiddlewareDispatcherFilepath(), $this)]);
        /** @var MiddlewareDefinition[] $middlewares */
        $middlewares = [];

        foreach ($classes as $class) {
            $definition = $this->getMiddlewareDefinition($class);

            if ($definition !== null) {
                $middlewares[] = $definition;
            }
        }

        foreach ($this->config->getAdditionalServices() as $additionalMiddleware) {
            if (is_string($additionalMiddleware)) {
                $className = $additionalMiddleware;
            } elseif ($additionalMiddleware instanceof ServiceDefinition) {
                $className = $additionalMiddleware->getClassName();
            } elseif ($additionalMiddleware instanceof MiddlewareDefinition) {
                $middlewares[] = $additionalMiddleware;
                continue;
            } else {
                continue;
            }

            if (class_exists($className)) {
                $class = new ReflectionClass($className);
                $definition = $this->getMiddlewareDefinition($class);

                if ($definition !== null) {
                    $middlewares[] = $definition;
                }
            }
        }

        usort($middlewares, static function (MiddlewareDefinition $a, MiddlewareDefinition $b) {
            // Sorting array by decreasing priority
            return ($a->getPriority() <=> $b->getPriority()) * -1;
        });

        $this->emitter->emitMiddlewareDispatcher($middlewares);
        $this->recordedMiddlewares = [];
        unset($middlewares);

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $middleware
     */
    private function getMiddlewareDefinition(ReflectionClass $middleware): ?MiddlewareDefinition
    {
        if (isset($this->recordedMiddlewares[$middleware->name])) {
            return null;
        }
        $this->recordedMiddlewares[$middleware->name] = true;

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

    public function supportsCompilation(): bool
    {
        return $this->config instanceof MiddlewareDispatcherCompilerConfiguration;
    }
}
