<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use Exception;
use Psr\EventDispatcher\StoppableEventInterface;
use ReflectionClass;
use Riaf\Configuration\EventDispatcherCompilerConfiguration;
use Riaf\PsrExtensions\EventDispatcher\Listener;
use RuntimeException;

class EventDispatcherCompiler extends BaseCompiler
{
    /** @var array<string, array<array{class: string, method: string, static: bool}>> */
    private array $listeners = [];

    /** @var array<string, bool> */
    private array $recordedClasses = [];

    /**
     * @throws Exception
     */
    public function compile(): bool
    {
        $this->timing->start(self::class);
        /** @var EventDispatcherCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getEventDispatcherFilepath());

        $classes = $this->analyzer->getUsedClasses($this->config->getProjectRoot(), [$this->outputFile]);

        foreach ($classes as $class) {
            if (!isset($this->recordedClasses[$class->name])) {
                $this->recordedClasses[$class->name] = true;
                $this->analyzeClass($class);
            }
        }

        foreach ($config->getAdditionalEventListeners() as $className) {
            if (!class_exists($className)) {
                throw new RuntimeException("Could not found additional listener $className");
            }

            if (!isset($this->recordedClasses[$className])) {
                $this->recordedClasses[$className] = true;
                $class = new ReflectionClass($className);
                $this->analyzeClass($class);
            }
        }

        $this->generateHeader($config->getEventDispatcherNamespace());
        $this->generateEventFunctions();
        $this->generateDispatchFunction();
        $this->generateEnding();

        $this->timing->stop(self::class);

        return true;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function analyzeClass(ReflectionClass $class): void
    {
        /** @var ReflectionClass<object> $class */
        $attributes = $class->getAttributes(Listener::class);

        foreach ($attributes as $attribute) {
            /** @var Listener $listener */
            $listener = $attribute->newInstance();
            $event = $listener->getTarget();
            $method = $listener->getMethod();

            if (!class_exists($event)) {
                // TODO: Exception
                throw new RuntimeException("$event not found!");
            }

            if (!$class->hasMethod($method)) {
                $this->timing->stop(self::class);
                // TODO: Exception
                throw new RuntimeException("$method not found in $class->name");
            }

            $reflectionMethod = $class->getMethod($method);

            if ($reflectionMethod->isAbstract() || $reflectionMethod->isPrivate() || $reflectionMethod->isProtected() || $reflectionMethod->isClosure()) {
                $this->timing->stop(self::class);
                // TODO: Exception
                throw new RuntimeException("$method is not callable");
            }

            if (!isset($this->listeners[$event])) {
                $this->listeners[$event] = [];
            }

            $this->listeners[$event][] = ['class' => $class->name, 'method' => $method, 'static' => $reflectionMethod->isStatic()];
        }
    }

    private function generateHeader(string $namespace): void
    {
        $this->writeLine('<?php');
        $this->writeLine(
            <<<HEADER
namespace $namespace;

class EventDispatcher implements \Psr\EventDispatcher\EventDispatcherInterface
{
    public function __construct(private \Psr\Container\ContainerInterface \$container)
    {
    }
HEADER
        );
    }

    private function generateEventFunctions(): void
    {
        foreach ($this->listeners as $event => $recordedListeners) {
            $this->writeLine(sprintf('public function dispatch%s(object $event): object', str_replace('\\', '_', $event)), 1);
            $this->writeLine('{', 2);

            /** @noinspection PhpUnhandledExceptionInspection */
            /** @psalm-suppress ArgumentTypeCoercion Is checked beforehand */
            /** @phpstan-ignore-next-line */
            $eventClass = new ReflectionClass($event);
            $isStoppable = $eventClass->implementsInterface(StoppableEventInterface::class);

            foreach ($recordedListeners as $recordedListener) {
                if ($isStoppable) {
                    $this->writeLine('if($event->isPropagationStopped()) return $event;', 2);
                }

                $class = $recordedListener['class'];
                $method = $recordedListener['method'];

                if ($recordedListener['static']) {
                    $this->writeLine("\$event = \\$class::$method(\$event);", 2);
                } else {
                    $this->writeLine("\$event = \$this->container->get(\"$class\")->$method(\$event);", 2);
                }
            }

            $this->writeLine('return $event;', 2);
            $this->writeLine('}', 1);
        }
    }

    private function generateDispatchFunction(): void
    {
        $this->writeLine('public function dispatch(object $event): object', 1);
        $this->writeLine('{', 1);

        foreach ($this->listeners as $event => $_) {
            $this->writeLine("if(\$event instanceof \\$event)", 2);
            $this->writeLine('{', 2);
            $this->writeLine(sprintf('return $this->dispatch%s($event);', str_replace('\\', '_', $event)), 3);
            $this->writeLine('}', 2);
            $this->writeLine();
        }

        $this->writeLine('return $event;', 2);
        $this->writeLine('}', 1);
    }

    private function generateEnding(): void
    {
        $this->writeLine('}');
    }

    public function supportsCompilation(): bool
    {
        return $this->config instanceof EventDispatcherCompilerConfiguration;
    }
}
