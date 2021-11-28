<?php

declare(strict_types=1);

namespace Riaf\Compiler\Analyzer;

use Exception;
use Generator;
use Iterator;
use JsonException;
use ReflectionClass;
use Riaf\Metrics\Timing;

class StandardAnalyzer implements AnalyzerInterface
{
    public function __construct(private Timing $timing)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getUsedClasses(string $projectRoot, array $forbiddenFiles = []): Iterator
    {
        $this->timing->start(self::class);
        $forbiddenFiles = @array_flip($forbiddenFiles);

        try {
            $autoloadedNamespaces = $this->getAutoloadNamespaces($projectRoot);
        } catch (Exception) {
            $this->timing->stop(self::class);

            return;
        }

        foreach ($autoloadedNamespaces as $namespace => $directory) {
            $dir = realpath($projectRoot . DIRECTORY_SEPARATOR . $directory);

            if ($dir === false) {
                $this->timing->stop(self::class);
                // TODO: Exception
                throw new Exception('Autoloaded Directory does not exist: ' . $directory);
            }

            $files = $this->getFilesInDirectory($dir);

            foreach ($files as $file) {
                if (isset($forbiddenFiles[$file])) {
                    continue;
                }

                $reflectionClass = $this->tryGetReflectionClass($namespace, $dir, $file);

                if ($reflectionClass === null) {
                    continue;
                }

                $this->timing->stop(self::class);
                yield $reflectionClass;
                $this->timing->start(self::class);
            }
        }
        $this->timing->stop(self::class);
    }

    /**
     * @return Generator<string, string>
     *
     * @throws JsonException
     * @throws Exception
     */
    private function getAutoloadNamespaces(string $projectRoot): Generator
    {
        $composerJson = "$projectRoot/composer.json";

        if (!file_exists($composerJson)) {
            // TODO: Exception
            throw new Exception();
        }

        $composerJsonData = file_get_contents($composerJson);

        if ($composerJsonData === false) {
            // TODO: Exception
            throw new Exception();
        }

        /** @var array{autoload: array{psr-4: array<string, string|string[]>}} $composerData */
        $composerData = json_decode($composerJsonData, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($composerData['autoload'])) {
            return [];
        }

        $autoload = $composerData['autoload'];

        if (!isset($autoload['psr-4'])) {
            return [];
        }

        $psr4 = $autoload['psr-4'];

        foreach ($psr4 as $namespace => $directory) {
            if (is_array($directory)) {
                foreach ($directory as $dir) {
                    yield $namespace => $dir;
                }
            } else {
                yield $namespace => $directory;
            }
        }
    }

    /**
     * @return Iterator<string>
     */
    private function getFilesInDirectory(string $directory): Iterator
    {
        $files = scandir($directory);

        if ($files === false) {
            return;
        }

        $files = array_diff($files, ['..', '.']);
        foreach ($files as $value) {
            $name = $directory . DIRECTORY_SEPARATOR . $value;
            if (is_dir($name)) {
                $generator = $this->getFilesInDirectory($name);

                foreach ($generator as $file) {
                    yield $file;
                }
            } else {
                yield $name;
            }
        }
    }

    /**
     * @return ReflectionClass<object>|null
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function tryGetReflectionClass(string $composerNamespace, string $mappedDirectory, string $file): ?ReflectionClass
    {
        $actualFilePath = str_replace($mappedDirectory . DIRECTORY_SEPARATOR, '', $file);
        $className = rtrim($actualFilePath, '.php');
        $namespace = str_replace('/', '\\', $className);

        if (class_exists($namespace) || interface_exists($namespace)) {
            return new ReflectionClass($namespace);
        }
        $namespace = $composerNamespace . $namespace;

        if (class_exists($namespace) || interface_exists($namespace)) {
            return new ReflectionClass($namespace);
        }

        return null;
    }
}
