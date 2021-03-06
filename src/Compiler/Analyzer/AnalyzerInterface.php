<?php

declare(strict_types=1);

namespace Riaf\Compiler\Analyzer;

use Iterator;
use ReflectionClass;

interface AnalyzerInterface
{
    /**
     * @param string[] $forbiddenFiles
     *
     * @return Iterator<ReflectionClass<object>>
     */
    public function getUsedClasses(string $projectRoot, array $forbiddenFiles = []): Iterator;
}
