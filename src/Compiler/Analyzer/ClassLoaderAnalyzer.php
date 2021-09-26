<?php

declare(strict_types=1);

namespace Riaf\Compiler\Analyzer;

use Composer\Autoload\ClassLoader;
use Iterator;
use ReflectionClass;
use RuntimeException;

class ClassLoaderAnalyzer implements AnalyzerInterface
{
    public function getUsedClasses(string $projectRoot): Iterator
    {
        throw new RuntimeException('This analyzer does not work. Because of classes like \'infection/extension-installer/src/Plugin.php\' that are registered as PSR-4 autoloads but parts of them (like this interface) cannot be autoloaded, this analyzer breaks completely and there is no way to check this beforehand or handle the error in any way.');
        /** @var ClassLoader $autoloader */
        $autoloader = require $projectRoot . '/vendor/autoload.php';
        $classMap = $autoloader->getClassMap();

        foreach ($classMap as $class => $file) {
            if (class_exists($class, false) || interface_exists($class, false)) {
                yield new ReflectionClass($class);
            }

            if (file_exists($file)) {
                if (!in_array($class, get_declared_classes()) && !in_array($class, get_declared_interfaces())) {
                    if (@include_once $file) {
                        yield new ReflectionClass($class);
                    }
                }
            }
        }
    }
}
