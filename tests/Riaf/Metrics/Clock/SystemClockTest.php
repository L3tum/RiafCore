<?php

declare(strict_types=1);

namespace Riaf\Metrics\Clock;

use PHPUnit\Framework\TestCase;

class SystemClockTest extends TestCase
{
    public function testReturnsCorrectTime(): void
    {
        $time = time();
        $systemTime = (new SystemClock())->getTime();

        // Wiggle room of ~1 second
        self::assertGreaterThanOrEqual($time, $systemTime);
        self::assertLessThanOrEqual($time + 1, $systemTime);
    }

    public function testReturnsCorrectMicrotime(): void
    {
        $time = microtime(true);
        $systemTime = (new SystemClock())->getMicroTime();

        // Wiggle room of ~100 microseconds
        self::assertGreaterThanOrEqual($time, $systemTime);
        self::assertLessThanOrEqual($time + 0.1, $systemTime);
    }
}
