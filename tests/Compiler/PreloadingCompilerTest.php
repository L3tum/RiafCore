<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use PHPUnit\Framework\TestCase;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;

class PreloadingCompilerTest extends TestCase
{
    private PreloadingCompiler $compiler;

    /**
     * @var SampleCompilerConfiguration
     */
    private $config;

    public function testUsesOpCacheCompileFile(): void
    {
        $this->compiler->compile();
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        $lines = array_slice(explode("\n", $content), 1);
        foreach ($lines as $line) {
            if (!empty($line)) {
                self::assertStringStartsWith('opcache_compile_file(', $line);
            }
        }
    }

    public function testAddsAdditionalFiles(): void
    {
        $this->compiler->compile();
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        self::assertStringContainsString('opcache_compile_file("' . $this->config->getProjectRoot() . '/bin/compile");', $content);
    }

    public function testAddsFilesFromVendor(): void
    {
        $this->compiler->compile();
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        self::assertStringContainsString('opcache_compile_file("' . $this->config->getProjectRoot() . '/vendor/', $content);
    }

    public function testDoesNotAddAdditionalFileIfAlreadyPreloaded(): void
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

            public function getAdditionalPreloadedFiles(): array
            {
                return ['src/Core.php'];
            }
        };

        $this->compiler = new PreloadingCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $this->config);
        $this->compiler->compile();
        $stream = $this->config->getFileHandle($this->compiler);
        fseek($stream, 0);
        $content = stream_get_contents($stream);
        $lines = array_slice(explode("\n", $content), 1);
        $foundOnce = false;
        $foundMoreThanOnce = false;
        foreach ($lines as $line) {
            if (str_contains($line, '/src/Core.php')) {
                if ($foundOnce) {
                    $foundMoreThanOnce = true;
                } else {
                    $foundOnce = true;
                }
            }
        }

        self::assertTrue($foundOnce);
        self::assertFalse($foundMoreThanOnce);
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

        $this->compiler = new PreloadingCompiler(new StandardAnalyzer(new Timing(new SystemClock())), new Timing(new SystemClock()), $this->config);
    }
}