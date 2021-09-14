<?php

declare(strict_types=1);

namespace Riaf\Compiler\Configuration;

interface MiddlewareDispatcherCompilerConfiguration
{
    public function getMiddlewareDispatcherNamespace(): string;

    public function getMiddlewareDispatcherFilepath(): string;

    /**
     * Must return an array of strings.
     * Values must be names of classes.
     *
     * E.g. return [MyMiddleware::class]
     *
     * @return string[]
     */
    public function getAdditionalMiddlewares(): array;
}
