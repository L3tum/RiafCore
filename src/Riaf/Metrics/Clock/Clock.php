<?php

declare(strict_types=1);

namespace Riaf\Metrics\Clock;

interface Clock
{
    public function getTime(): int;

    public function getMicroTime(): float;
}
