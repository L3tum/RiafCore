<?php

declare(strict_types=1);

namespace Riaf\Compiler\Analyzer;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Riaf\Compiler\ContainerCompiler;
use Riaf\Core;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;

class StandardAnalyzerTest extends TestCase
{
    private StandardAnalyzer $analyzer;

    public function testReturnsReflectionClasses(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $classes = $this->analyzer->getUsedClasses($projectRoot);

        foreach ($classes as $class) {
            self::assertInstanceOf(ReflectionClass::class, $class);
        }
    }

    public function testReturnsAtLeastClasses(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $classes = $this->analyzer->getUsedClasses($projectRoot);
        $classesToFind = [
            Core::class => false,
            StandardAnalyzer::class => false,
            ContainerCompiler::class => false,
        ];

        foreach ($classes as $class) {
            if (isset($classesToFind[$class->getName()])) {
                self::assertFalse($classesToFind[$class->getName()]);
                $classesToFind[$class->getName()] = true;
            }
        }

        foreach ($classesToFind as $class) {
            self::assertTrue($class);
        }
    }

    public function testReturnsInterfaces(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $classes = $this->analyzer->getUsedClasses($projectRoot);
        $foundInterface = false;

        foreach ($classes as $class) {
            /** @var ReflectionClass $class */
            if ($class->isInterface()) {
                $foundInterface = true;
                break;
            }
        }

        self::assertTrue($foundInterface);
    }

    public function testReturnsAbstractClasses(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $classes = $this->analyzer->getUsedClasses($projectRoot);
        $foundAbstractClass = false;

        foreach ($classes as $class) {
            /** @var ReflectionClass $class */
            if ($class->isAbstract()) {
                $foundAbstractClass = true;
                break;
            }
        }

        self::assertTrue($foundAbstractClass);
    }

    protected function setUp(): void
    {
        $this->analyzer = new StandardAnalyzer(new Timing(new SystemClock()));
    }
}
