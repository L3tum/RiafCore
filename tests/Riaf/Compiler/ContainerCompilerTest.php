<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Events\CoreEvent;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;

/**
 * @runTestsInSeparateProcesses
 */
class ContainerCompilerTest extends TestCase
{
    private SampleCompilerConfiguration $config;

    private ContainerCompiler $compiler;

    public function testImplementsContainerInterface(): void
    {
        $this->compiler->compile();
        $container = $this->getContainer();
        self::assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testImplementsContainerInterfaceGetMethod(): void
    {
        $this->compiler->compile();
        $container = $this->getContainer();
        self::assertTrue(method_exists($container, 'get'));
    }

    public function testImplementsContainerInterfaceHasMethod(): void
    {
        $this->compiler->compile();
        $container = $this->getContainer();
        self::assertTrue(method_exists($container, 'has'));
    }

    public function testCanGetContainerInterface(): void
    {
        $this->compiler->compile();
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        self::assertSame($container, $container->get('Riaf\\Container'));
        self::assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testCanHasContainerInterface(): void
    {
        $this->compiler->compile();
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        self::assertTrue($container->has('Riaf\\Container'));
        self::assertTrue($container->has(ContainerInterface::class));
    }

    public function testMapsAdditionalClasses(): void
    {
        $this->compiler->compile();
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        self::assertTrue($container->has(CoreEvent::class));
    }

    protected function setUp(): void
    {
        $this->config = new class() extends SampleCompilerConfiguration {
            private $stream = null;

            public function getFileHandle(BaseCompiler $compiler)
            {
                if ($this->stream === null) {
                    $this->stream = fopen('php://memory', 'wb+');
                }

                return $this->stream;
            }
        };

        $this->compiler = new ContainerCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $this->config);
    }

    /**
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private function getContainer(): \Riaf\Container
    {
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        eval('?>' . $content);

        return new \Riaf\Container();
    }
}
