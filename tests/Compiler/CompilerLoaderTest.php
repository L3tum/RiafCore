<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CompilerLoaderTest extends TestCase
{
    private CompilerLoader $loader;

    public function testReturnsNullWithInvalidClassName(): void
    {
        self::assertNull($this->loader->loadCompiler('InvalidClassName'));
    }

    public function testReturnsNullIfClassDoesNotExtendBaseCompiler(): void
    {
        self::assertNull($this->loader->loadCompiler(self::class));
    }

    public function testReturnsCompiler(): void
    {
        self::assertInstanceOf(ReflectionClass::class, $this->loader->loadCompiler(RouterCompiler::class));
    }

    protected function setUp(): void
    {
        $this->loader = new CompilerLoader();
    }
}
