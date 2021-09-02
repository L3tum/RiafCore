<?php

namespace Riaf;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Riaf\Events\BootEvent;
use Riaf\Events\RequestEvent;
use Riaf\Events\ResponseEvent;
use Riaf\Events\TerminateEvent;
use Riaf\ResponseEmitter\ResponseEmitterInterface;
use Stringable;

class Core implements RequestHandlerInterface
{
    protected ?EventDispatcherInterface $eventDispatcher;
    protected ?ResponseEmitterInterface $responseEmitter;
    protected ?LoggerInterface $logger;

    /**
     * @param ContainerInterface      $container            The container to fetch services from
     * @param RequestHandlerInterface $middlewareDispatcher The middleware dispatcher to issue the requests to
     */
    public function __construct(
        protected ContainerInterface $container,
        protected RequestHandlerInterface $middlewareDispatcher,
    ) {
        $this->eventDispatcher = $container->has(EventDispatcherInterface::class) ? $container->get(EventDispatcherInterface::class) : null;
        $this->responseEmitter = $container->has(ResponseEmitterInterface::class) ? $container->get(ResponseEmitterInterface::class) : null;
        $this->logger = $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null;
        $this->fireBootEvent();
    }

    private function fireBootEvent(): void
    {
        if (null !== $this->eventDispatcher) {
            $this->log('Firing BootEvent');
            $event = new BootEvent($this);
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * Using a NullLogger unfortunately means that String Processing etc. is still done although it has no effect.
     * As such, this is a cheap wrapper.
     *
     * @phpstan-ignore-next-line
     */
    private function log(string $message, array $context = [], string $level = LogLevel::DEBUG): void
    {
        if (null !== $this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->fireRequestEvent($request);
        $response = $this->middlewareDispatcher->handle($request);
        $response = $this->fireResponseEvent($request, $response);

        $this->dispatchResponse($response);

        $this->fireTerminateEvent($request, $response);

        return $response;
    }

    private function fireRequestEvent(ServerRequestInterface $request): ServerRequestInterface
    {
        if (null !== $this->eventDispatcher) {
            $this->log('Firing RequestEvent');
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
            $this->log('Firing ResponseEvent');
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
            $this->log('Dispatching Response');
            $this->responseEmitter->emitResponse($response);
        }
    }

    private function fireTerminateEvent(ServerRequestInterface $request, ResponseInterface $response): void
    {
        if (null !== $this->eventDispatcher) {
            $this->log('Firing TerminateEvent');
            $event = new TerminateEvent($this, $request, $response);
            $this->eventDispatcher->dispatch($event);
        }
    }
}
