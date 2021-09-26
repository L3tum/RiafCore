<?php

declare(strict_types=1);

namespace Riaf\PsrExtensions\Middleware;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MiddlewareTest extends TestCase
{
    public function testAcceptsPriorityAndReturnsAsIs(): void
    {
        $middleware = new Middleware(100);
        self::assertEquals(100, $middleware->getPriority());
    }

    public function testDefaultPriorityIsZero(): void
    {
        $middleware = new Middleware();
        self::assertEquals(0, $middleware->getPriority());
    }

    public function testWorksAsAttribute(): void
    {
        $class = new ReflectionClass(TestMiddlewareClass::class);
        self::assertNotEmpty($class->getAttributes(Middleware::class));
        $middleware = $class->getAttributes(Middleware::class)[0];
        $middlewareInstance = $middleware->newInstance();
        self::assertEquals(0, $middlewareInstance->getPriority());
    }
}

#[Middleware]
class TestMiddlewareClass
{
}
