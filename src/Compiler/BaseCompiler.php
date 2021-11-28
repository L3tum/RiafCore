<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use Riaf\Compiler\Analyzer\AnalyzerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;

abstract class BaseCompiler
{
    protected AnalyzerInterface $analyzer;

    protected Timing $timing;

    protected LoggerInterface $logger;

    #[Pure]
    public function __construct(protected BaseConfiguration $config, ?AnalyzerInterface $analyzer = null, ?Timing $timing = null, ?LoggerInterface $logger = null)
    {
        $this->timing = $timing ?? new Timing(new SystemClock());
        $this->analyzer = $analyzer ?? new StandardAnalyzer($this->timing);
        $this->logger = $logger ?? new NullLogger();
    }

    abstract public function compile(): bool;

    abstract public function supportsCompilation(): bool;

    /**
     * @return ReflectionClass<object>|null
     *
     * @internal
     */
    public static function getReflectionClassFromReflectionType(?ReflectionType $reflectionType): ?ReflectionClass
    {
        if ($reflectionType === null) {
            return null;
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            $typeName = $reflectionType->getName();

            if (class_exists($typeName) || interface_exists($typeName)) {
                try {
                    return new ReflectionClass($typeName);
                } catch (ReflectionException) {
                    // Intentionally left blank
                }
            }
        }

        return null;
    }

    protected function getOutputFile(string $filePath): string
    {
        return $this->config->getProjectRoot() . $filePath;
    }
}
