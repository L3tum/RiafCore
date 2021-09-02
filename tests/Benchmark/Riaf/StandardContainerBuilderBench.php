<?php

namespace Benchmark\Riaf;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use Riaf\PsrExtensions\Container\IdNotFoundException;
use Riaf\PsrExtensions\Container\StandardContainerBuilder;

#[BeforeMethods('setUp')]
class StandardContainerBuilderBench
{
    private StandardContainerBuilder $container;

    #[Iterations(30)]
    public function benchContainerGetCold(): void
    {
        $this->container->get('hello');
    }

    #[Iterations(10)]
    #[Revs(100)]
    public function benchContainerGetNormal(): void
    {
        $this->container->get('hello');
    }

    #[Iterations(10)]
    #[Revs(100)]
    #[BeforeMethods('warmUp')]
    public function benchContainerGetWarm(): void
    {
        $this->container->get('hello');
    }

    #[Iterations(10)]
    #[Revs(100)]
    public function benchContainerGetDoesNotExist(): void
    {
        try {
            $this->container->get('doesnotexist');
        } catch (IdNotFoundException) {
            // Intentionally left blank
        }
    }

    #[Iterations(10)]
    #[Revs(100)]
    public function benchContainerHasExists(): void
    {
        $this->container->has('hello');
    }

    #[Iterations(10)]
    #[Revs(100)]
    public function benchContainerHasDoesNotExist(): void
    {
        $this->container->has('doesnotexist');
    }

    public function warmUp(): void
    {
        $this->container->get('hello');
    }

    public function setUp(): void
    {
        $this->container = new StandardContainerBuilder();
        $this->container->set('hello', static function () {
            return 'hello';
        });
    }
}
