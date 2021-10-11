<?php

declare(strict_types=1);

namespace Riaf\Metrics;

use PHPUnit\Framework\TestCase;
use Riaf\Metrics\Clock\SystemClock;

class TimingTest extends TestCase
{
    private Timing $timing;

    public function testDoesNotAddStartToTimings(): void
    {
        $this->timing->start('test');
        $timings = $this->timing->getTimings();

        self::assertArrayNotHasKey('test', $timings);
    }

    public function testAddsStopToTimings(): void
    {
        $this->timing->start('test');
        usleep(500);
        $this->timing->stop('test');
        $stopTiming = $this->timing->getTimings()['test'];
        self::assertLessThan(1, $stopTiming);
    }

    public function testAddsStopToTimingsWithKey(): void
    {
        $this->timing->start('test');
        usleep(500);
        $this->timing->stop('test');
        $stopTiming = $this->timing->getTiming('test');
        self::assertLessThan(1, $stopTiming);
    }

    public function testAddsLabelsAtStart(): void
    {
        $this->timing->start('test', ['test' => 'test']);
        $labels = $this->timing->getLabels('test');
        self::assertNotEmpty($labels);
        self::assertArrayHasKey('test', $labels);
        self::assertEquals('test', $labels['test']);
    }

    public function testAddsLabelsAtStop(): void
    {
        $this->timing->start('test');
        $this->timing->stop('test', ['test' => 'test']);
        $labels = $this->timing->getLabels('test');
        self::assertNotEmpty($labels);
        self::assertArrayHasKey('test', $labels);
        self::assertEquals('test', $labels['test']);
    }

    public function testOverwritesLabelsAtStop(): void
    {
        $this->timing->start('test', ['something' => 'else']);
        $this->timing->stop('test', ['test' => 'test']);
        $labels = $this->timing->getLabels('test');
        self::assertNotEmpty($labels);
        self::assertArrayHasKey('test', $labels);
        self::assertEquals('test', $labels['test']);
    }

    public function testAddsTimingWithRecord(): void
    {
        $this->timing->record('test', 1.0);
        $timings = $this->timing->getTimings();
        self::assertArrayHasKey('test', $timings);
        self::assertEquals(1.0, $timings['test']);
    }

    public function testAddsLabelsWithRecord(): void
    {
        $this->timing->record('test', 1.0, ['test' => 'test']);
        $labels = $this->timing->getLabels('test');
        self::assertNotEmpty($labels);
        self::assertArrayHasKey('test', $labels);
        self::assertEquals('test', $labels['test']);
    }

    public function testOverwritesTimingWithRecord(): void
    {
        $this->timing->start('test');
        $this->timing->record('test', 1.0);
        $timings = $this->timing->getTimings();
        self::assertArrayHasKey('test', $timings);
        self::assertEquals(1.0, $timings['test']);
    }

    public function testOverwritesLabelsWithRecord(): void
    {
        $this->timing->start('test', ['something' => 'else']);
        $this->timing->record('test', 1.0, ['test' => 'test']);
        $labels = $this->timing->getLabels('test');
        self::assertNotEmpty($labels);
        self::assertArrayHasKey('test', $labels);
        self::assertEquals('test', $labels['test']);
    }

    public function testReturnsEmptyLabelArray(): void
    {
        $labels = $this->timing->getLabels('doesnotexist');
        self::assertEmpty($labels);
    }

    public function testOverwritesStartIfCalledMultipleTimes(): void
    {
        $this->timing->start('test');
        $start = microtime(true);
        usleep(100);
        $this->timing->start('test');
        $this->timing->stop('test');
        $total = microtime(true) - $start;

        self::assertLessThan($total, $this->timing->getTimings()['test']);
    }

    protected function setUp(): void
    {
        $this->timing = new Timing(new SystemClock());
    }
}
