<?php

declare(strict_types=1);

namespace Riaf\TestCases\EventDispatcher;

use Riaf\PsrExtensions\EventDispatcher\Listener;

#[Listener(StaticEve::class, 'onStatic')]
class StaticMethodListener
{
    private static int $counter = 0;

    public static function onStatic(object $event): object
    {
        ++self::$counter;

        return $event;
    }

    public static function getCounter(): int
    {
        return self::$counter;
    }
}
