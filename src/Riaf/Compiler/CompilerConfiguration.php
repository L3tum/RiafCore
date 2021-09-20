<?php

declare(strict_types=1);

namespace Riaf\Compiler;

use function dirname;
use LogicException;
use ReflectionObject;

abstract class CompilerConfiguration
{
    protected ?string $projectDir = null;

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string The project root dir
     */
    public function getProjectRoot(): string
    {
        if ($this->projectDir === null) {
            $reflectionObject = new ReflectionObject($this);
            $dir = $reflectionObject->getFileName();

            if (!is_file($dir)) {
                throw new LogicException('Cannot auto-detect project dir for config of class ' . $reflectionObject->name);
            }

            $dir = $rootDir = dirname($dir);
            while (!is_file($dir . '/composer.json')) {
                if ($dir === dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }

    /**
     * @return resource|null
     */
    public function getFileHandle(BaseCompiler $compiler)
    {
        return null;
    }

    /**
     * @return string[]
     */
    public function getAdditionalCompilers(): array
    {
        return [];
    }

    public function isDevelopmentMode(): bool
    {
        return ($_SERVER['APP_ENV'] ?? 'prod') === 'dev';
    }
}
