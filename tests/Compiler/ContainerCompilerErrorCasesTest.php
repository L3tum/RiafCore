<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Configuration\ParameterDefinition;
use Riaf\Configuration\ServiceDefinition;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;
use Riaf\TestCases\Container\IndirectSelfDependencyOne;
use Riaf\TestCases\Container\InjectedServiceSkipParameter;
use Riaf\TestCases\Container\SelfDependency;
use RuntimeException;

class ContainerCompilerErrorCasesTest extends TestCase
{
    public function testHandlesInjectedServiceSkipParameter(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [
                    InjectedServiceSkipParameter::class => ServiceDefinition::create(InjectedServiceSkipParameter::class)
                        ->setParameters([
                            ParameterDefinition::createInjected('compiler', RequestInterface::class)
                                ->withFallback(ParameterDefinition::createSkipIfNotFound('compiler')),
                        ]),
                ];
            }
        };
        $compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);
        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testHandlesSelfDependency(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [
                    SelfDependency::class => SelfDependency::class,
                ];
            }
        };
        $compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);
        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testHandlesIndirectSelfDependency(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }

            public function getAdditionalServices(): array
            {
                return [
                    IndirectSelfDependencyOne::class => IndirectSelfDependencyOne::class,
                ];
            }
        };
        $compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);
        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }

    public function testHandlesManuallyAddedNonExistingService(): void
    {
        $config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }
        };
        $compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $config);
        $compiler->addService('doesnotexist', ServiceDefinition::create('doesnotexist'));
        $this->expectException(RuntimeException::class);
        $compiler->compile();
    }
}
