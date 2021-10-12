<?php

declare(strict_types=1);

//namespace Riaf\Compiler\Analyzer;
//
//use Composer\Autoload\ClassLoader;
//use Exception;
//use Iterator;
//use ParseError;
//use ReflectionClass;
//use Riaf\Metrics\Timing;
//use RuntimeException;
//use Throwable;
//
///**
// * @codeCoverageIgnore
// */
//class ClassLoaderAnalyzer implements AnalyzerInterface
//{
//    public function __construct(private Timing $timing)
//    {
//    }
//
//    public function getUsedClasses(string $projectRoot, array $forbiddenFiles = []): Iterator
//    {
//        $this->timing->start(self::class);
//        /** @var ClassLoader $autoloader */
//        $autoloader = require $projectRoot . '/vendor/autoload.php';
//
//        if (!$autoloader->isClassMapAuthoritative()) {
//            throw new RuntimeException("You need to generate an authoritative classmap");
//        }
//
//        $classMap = $autoloader->getClassMap();
//
//        foreach ($classMap as $class => $file) {
//            // Skip Traits
//            if (trait_exists($class, false)) {
//                continue;
//            }
//
//            if (class_exists($class, false) || interface_exists($class, false)) {
//                $this->timing->stop(self::class);
//                yield new ReflectionClass($class);
//                $this->timing->start(self::class);
//                continue;
//            }
//
//            if (!@is_readable($file)) {
//                continue;
//            }
//
//            try {
//                @eval(substr(file_get_contents($file), 6));
//            } catch (ParseError | Throwable | Exception $e) {
////                echo "Skipped $class because {$e->getMessage()}" . PHP_EOL;
//                continue;
//            }
//
//            if (class_exists($class) || interface_exists($class)) {
//                $this->timing->stop(self::class);
//                yield new ReflectionClass($class);
//                $this->timing->start(self::class);
//            }
//        }
//    }
//}
