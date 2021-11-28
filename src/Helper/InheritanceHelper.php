<?php

declare(strict_types=1);

namespace Riaf\Helper;

use ReflectionClass;

class InheritanceHelper
{
    public static function usesTrait(string $traitName, ReflectionClass $class): bool
    {
        if (in_array($traitName, $class->getTraitNames())) {
            return true;
        }

        $extensionClass = $class->getParentClass();

        if ($extensionClass !== false && self::usesTrait($traitName, $extensionClass)) {
            return true;
        }

        return false;
    }
}
