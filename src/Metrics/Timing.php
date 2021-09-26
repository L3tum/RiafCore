<?php

declare(strict_types=1);

namespace Riaf\Metrics;

use JetBrains\PhpStorm\ArrayShape;
use Riaf\Metrics\Clock\Clock;

final class Timing
{
    /**
     * @var array<string, float>
     */
    private array $timings = [];

    /**
     * @var array<string, float>
     */
    private array $startTimings = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $labels = [];

    public function __construct(private Clock $clock)
    {
    }

    /**
     * @param array<string, string> $labels
     */
    public function start(string $key, array $labels = []): void
    {
        $this->startTimings[$key] = $this->clock->getMicroTime();

        if (!empty($labels)) {
            $this->labels[$key] = $labels;
        }
    }

    /**
     * @param array<string, string> $labels
     */
    public function stop(string $key, array $labels = []): float
    {
        if (!isset($this->startTimings[$key])) {
            return 0.0;
        }

        $total = $this->clock->getMicroTime() - $this->startTimings[$key];
        unset($this->startTimings[$key]);
        $this->timings[$key] = $total;
        if (!empty($labels)) {
            $this->labels[$key] = $labels;
        }

        return $total;
    }

    /**
     * @param array<string, string> $labels
     */
    public function record(string $key, float $timing, array $labels = []): void
    {
        $this->timings[$key] = $timing;
        if (!empty($labels)) {
            $this->labels[$key] = $labels;
        }
    }

    /**
     * @return array<string, float>
     */
    #[ArrayShape(['string' => 'float'])]
    public function getTimings(): array
    {
        return $this->timings;
    }

    /**
     * @param string $key
     *
     * @return array<string, string>
     */
    #[ArrayShape(['string' => 'string'])]
    public function getLabels(string $key): array
    {
        return $this->labels[$key] ?? [];
    }
}
