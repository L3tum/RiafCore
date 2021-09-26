<?php

declare(strict_types=1);

namespace Riaf\Routing;

use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    private Route $route;

    public function testMergeRoutesInCorrectOrder(): void
    {
        $route = $this->route->mergeRoute('/hey')->getRoute();
        self::assertEquals('/hey/', $route);
    }

    public function testReturnsCorrectRequirement(): void
    {
        $requirement = $this->route->getRequirement('id');
        self::assertEquals('\\d', $requirement);
    }

    public function testReturnsNullWithNoRequirement(): void
    {
        $requirement = $this->route->getRequirement('noavailable');
        self::assertNull($requirement);
    }

    public function testMergesRequirements(): void
    {
        $requirements = $this->route->mergeRequirements(['another' => '\\s'])->getRequirements();

        self::assertArrayHasKey('id', $requirements);
        self::assertArrayHasKey('another', $requirements);
    }

    public function testOverwritesExistingRequirements(): void
    {
        $requirements = $this->route->mergeRequirements(['id' => '\\s'])->getRequirements();
        self::assertArrayHasKey('id', $requirements);
        self::assertCount(1, $requirements);
    }

    protected function setUp(): void
    {
        $this->route = new Route('/', 'GET', ['id' => '\\d']);
    }
}
