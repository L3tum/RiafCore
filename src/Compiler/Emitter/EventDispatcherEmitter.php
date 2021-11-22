<?php

declare(strict_types=1);

namespace Riaf\Compiler\Emitter;

use Exception;
use Psr\EventDispatcher\StoppableEventInterface;
use ReflectionClass;
use Riaf\Configuration\EventDispatcherCompilerConfiguration;

class EventDispatcherEmitter extends BaseEmitter
{
    /**
     * @var array<string, array<array{class: string, method: string, static: bool}>>
     */
    private array $listeners;

    /**
     * @param array<string, array<array{class: string, method: string, static: bool}>> $listeners
     *
     * @throws Exception
     */
    public function emitEventDispatcher(array &$listeners): void
    {
        $this->listeners = &$listeners;

        /** @var EventDispatcherCompilerConfiguration $config */
        $config = $this->config;
        $this->openResultFile($config->getEventDispatcherFilepath());
        $this->generateHeader($config->getEventDispatcherNamespace());
        $this->generateEventFunctions();
        $this->generateDispatchFunction();
        $this->generateEnding();
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

            /**
             * @noinspection PhpUnhandledExceptionInspection
             * @psalm-suppress ArgumentTypeCoercion Is checked beforehand
             * @phpstan-ignore-next-line
             */
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

        if (count($this->listeners) > 0) {
            $this->writeLine('return match($event::class)', 2);
            $this->writeLine('{', 2);

            foreach ($this->listeners as $event => $_) {
                $this->writeLine(sprintf('"%s" => $this->dispatch%s($event),', $event, str_replace('\\', '_', $event)), 3);
            }
            $this->writeLine('default => $event', 3);
            $this->writeLine('};', 2);
        } else {
            $this->writeLine('return $event;', 2);
        }

        $this->writeLine('}', 1);
    }

    private function generateEnding(): void
    {
        $this->writeLine('}');
    }
}
