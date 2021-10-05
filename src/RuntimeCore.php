<?php

declare(strict_types=1);

namespace Riaf;

use Exception;
use Psr\Container\ContainerInterface;
use Riaf\Compiler\Analyzer\StandardAnalyzer;
use Riaf\Compiler\BaseCompiler;
use Riaf\Compiler\ContainerCompiler;
use Riaf\Compiler\MiddlewareDispatcherCompiler;
use Riaf\Compiler\RouterCompiler;
use Riaf\Configuration\BaseConfiguration;
use Riaf\Metrics\Clock\SystemClock;
use Riaf\Metrics\Timing;
use RuntimeException;

class RuntimeCore extends Core
{
    /** @var string[] */
    private const COMPILERS = [
        RouterCompiler::class,
        MiddlewareDispatcherCompiler::class,
        ContainerCompiler::class,
    ];

    /**
     * @throws Exception
     */
    public function __construct(BaseConfiguration $config, ?ContainerInterface $container = null)
    {
        $compilers = array_merge($config->getAdditionalCompilers(), self::COMPILERS);
        $timing = new Timing(new SystemClock());
        $analyzer = new StandardAnalyzer($timing);

        foreach ($compilers as $compilerClass) {
            if (!class_exists($compilerClass)) {
                throw new RuntimeException("Missing compiler $compilerClass");
            }

            /** @var BaseCompiler $compiler */
            $compiler = new $compilerClass($analyzer, $timing, $config);
            if ($compiler->supportsCompilation()) {
                $compiler->compile();
            }
        }

        parent::__construct($config, $container);
    }
}
