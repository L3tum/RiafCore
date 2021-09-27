<?php

declare(strict_types=1);

namespace Riaf;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Riaf\Configuration\BaseConfiguration;

class CoreBuilder implements RequestHandlerInterface
{
    private Core $core;

    /**
     * @throws Exception
     */
    public function __construct(protected BaseConfiguration $config, protected ?ContainerInterface $container = null)
    {
        $this->core = $config->isDevelopmentMode() ? new RuntimeCore($config, $container) : new Core($config, $container);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->core->handle($request);
    }

    public function createRequestFromGlobals(): ServerRequestInterface
    {
        return $this->core->createRequestFromGlobals();
    }
}
