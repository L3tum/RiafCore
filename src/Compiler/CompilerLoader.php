<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use ReflectionClass;

class CompilerLoader
{
    /**
     * @return ReflectionClass<BaseCompiler>|null
     */
    public function loadCompiler(string $compiler): ?ReflectionClass
    {
        if (class_exists($compiler)) {
            /** @var ReflectionClass<BaseCompiler> $instance */
            $instance = new ReflectionClass($compiler);

            if ($instance->isSubclassOf(BaseCompiler::class)) {
                return $instance;
            }
        }

        return null;
    }
}
