<?php

declare(strict_types=1);

namespace Riaf;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Riaf\Compiler\CompilerConfiguration;
use Riaf\Events\BootEvent;
use Riaf\Events\RequestEvent;
use Riaf\Events\ResponseEvent;
use Riaf\Events\TerminateEvent;
use Riaf\ResponseEmitter\ResponseEmitterInterface;

class Core extends AbstractCore implements RequestHandlerInterface
{
    protected ?EventDispatcherInterface $eventDispatcher = null;

    protected ?ResponseEmitterInterface $responseEmitter = null;

    protected LoggerInterface $logger;

    /**
     * @param CompilerConfiguration   $config    The config
     * @param ContainerInterface|null $container The container to fetch services from
     */
    public function __construct(
        CompilerConfiguration $config,
        ?ContainerInterface $container = null,
    ) {
        parent::__construct($config, $container);
        // Unset both as the properties should be used
        unset($config, $container);
        /* @psalm-suppress PossiblyNullReference It's checked in AbstractCore and an exception thrown if null. */
        $this->eventDispatcher = $this->container->has(EventDispatcherInterface::class) ? $this->container->get(EventDispatcherInterface::class) : null;
        /* @psalm-suppress PossiblyNullReference It's checked in AbstractCore and an exception thrown if null. */
        $this->responseEmitter = $this->container->has(ResponseEmitterInterface::class) ? $this->container->get(ResponseEmitterInterface::class) : null;
        /* @psalm-suppress PossiblyNullReference It's checked in AbstractCore and an exception thrown if null. */
        $this->logger = $this->container->has(LoggerInterface::class) ? $this->container->get(LoggerInterface::class) : new NullLogger();
        $this->fireBootEvent();
    }

    private function fireBootEvent(): void
    {
        if (null !== $this->eventDispatcher) {
            $this->logger->debug('Firing BootEvent');
            $event = new BootEvent($this);
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->fireRequestEvent($request);
        /** @psalm-suppress PossiblyNullReference It's checked in AbstractCore and an exception thrown if null. */
        $response = $this->requestHandler->handle($request);
        $response = $this->fireResponseEvent($request, $response);

        $this->dispatchResponse($response);

        $this->fireTerminateEvent($request, $response);

        return $response;
    }

    private function fireRequestEvent(ServerRequestInterface $request): ServerRequestInterface
    {
        if (null !== $this->eventDispatcher) {
            $this->logger->debug('Firing RequestEvent');
            $event = new RequestEvent($request);
            /** @var RequestEvent $event */
            $event = $this->eventDispatcher->dispatch($event);

            return $event->getRequest();
        }

        return $request;
    }

    private function fireResponseEvent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (null !== $this->eventDispatcher) {
            $this->logger->debug('Firing ResponseEvent');
            $event = new ResponseEvent($request, $response);
            /** @var ResponseEvent $event */
            $event = $this->eventDispatcher->dispatch($event);

            return $event->getResponse();
        }

        return $response;
    }

    private function dispatchResponse(ResponseInterface $response): void
    {
        if (null !== $this->responseEmitter) {
            $this->logger->debug('Dispatching Response');
            $this->responseEmitter->emitResponse($response);
        }
    }

    private function fireTerminateEvent(ServerRequestInterface $request, ResponseInterface $response): void
    {
        if (null !== $this->eventDispatcher) {
            $this->logger->debug('Firing TerminateEvent');
            $event = new TerminateEvent($this, $request, $response);
            $this->eventDispatcher->dispatch($event);
        }
    }
}
