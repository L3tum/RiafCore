<?php

namespace Riaf\PsrExtensions\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class StandardContainerBuilderTest extends TestCase
{
    private StandardContainerBuilder $builder;

    public function testImplementsContainerInterface(): void
    {
        self::assertInstanceOf(ContainerBuilderInterface::class, $this->builder);
        self::assertInstanceOf(ContainerInterface::class, $this->builder);
    }

    public function testAcceptsCallable(): void
    {
        $callable = static function () {
            return 'hello';
        };

        $this->builder->set('hello', $callable);
        self::assertEquals('hello', $this->builder->get('hello'));
    }

    public function testInstantiatesClassOnlyOnce(): void
    {
        $counter = 0;
        $callable = static function () use (&$counter) {
            ++$counter;

            return 'hello';
        };
        $this->builder->set('hello', $callable);
        $this->builder->get('hello');
        $this->builder->get('hello');
        self::assertEquals(1, $counter);
    }

    public function testOverwritesSameId(): void
    {
        $counter = 0;
        $callable = static function () use (&$counter) {
            ++$counter;

            return 'hello';
        };
        $callable2 = static function () {
            return 'hello';
        };
        $this->builder->set('hello', $callable);
        $this->builder->set('hello', $callable2);
        $this->builder->get('hello');
        self::assertEquals(0, $counter);
    }

    public function testThrowsOnNotFound(): void
    {
        /* @phpstan-ignore-next-line */
        $this->expectException(NotFoundExceptionInterface::class);
        $this->builder->get('doesnotexist');
    }

    public function testHasReturnsTrueIfFound(): void
    {
        $callable = static function () {
            return 'hello';
        };
        $this->builder->set('hello', $callable);
        self::assertTrue($this->builder->has('hello'));
    }

    public function testHasReturnsFalseIfNotFound(): void
    {
        self::assertFalse($this->builder->has('doesnotexist'));
    }

    public function testBuildContainerReturnsItself(): void
    {
        self::assertInstanceOf(StandardContainerBuilder::class, $this->builder->buildContainer());
    }

    protected function setUp(): void
    {
        $this->builder = new StandardContainerBuilder();
    }
}
