<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

abstract class BaseTestCase
{
    private int $called = 0;

    public function getCalled(): int
    {
        return $this->called;
    }

    protected function recordCalled(): void
    {
        ++$this->called;
    }
}
