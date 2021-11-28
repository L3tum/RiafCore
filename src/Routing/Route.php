<?php

declare(strict_types=1);

namespace Riaf\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Route
{
    /**
     * Route constructor.
     *
     * @param string                $route
     * @param string                $method
     * @param array<string, string> $requirements A map of key (parameter) and value (requirement), where the value is a regex string
     */
    public function __construct(private string $route, private string $method = 'GET', private array $requirements = [])
    {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, string>
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getRequirement(string $parameter): ?string
    {
        return $this->requirements[$parameter] ?? null;
    }

    public function mergeRoute(string $route): Route
    {
        $this->route = $route . $this->route;

        return $this;
    }

    /** @param array<string, string> $requirements */
    public function mergeRequirements(array $requirements): Route
    {
        $this->requirements = array_merge($requirements, $this->requirements);

        return $this;
    }
}
