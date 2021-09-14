<?php

declare(strict_types=1);

namespace Riaf\Metrics\Clock;

class SystemClock implements Clock
{
    public function getTime(): int
    {
        return time();
    }

    public function getMicroTime(): float
    {
        return microtime(true);
    }
}
