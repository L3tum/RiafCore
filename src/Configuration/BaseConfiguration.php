<?php

declare(strict_types=1);

namespace Riaf\Configuration;

use function dirname;
use LogicException;
use ReflectionObject;
use Riaf\Compiler\BaseCompiler;
use RuntimeException;

abstract class BaseConfiguration
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

            if ($dir === false) {
                throw new RuntimeException('Cannot get file name for config of class ' . $reflectionObject->name);
            }

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
        return ($_SERVER['APP_ENV'] ?? 'dev') === 'dev';
    }

    /**
     * Must return an array of key-value strings
     * Key: A class (can be abstract) or interface, or alias
     * Value: An actual class-string, ServiceDefinition or MiddlewareDefinition.
     *
     * If Key is unequal Value, then Value is treated as the implementation for Key
     * making both Key and Value available for injection.
     *
     * @return array<string, ServiceDefinition|MiddlewareDefinition|string>
     * @noinspection PhpDocSignatureInspection
     */
    public function getAdditionalServices(): array
    {
        return [];
    }
}
