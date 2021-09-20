<?php

declare(strict_types=1);

namespace Riaf\Compiler\Analyzer;

use Exception;
use Iterator;
use JsonException;
use ReflectionClass;
use ReflectionException;
use Riaf\Metrics\Timing;

class StandardAnalyzer implements AnalyzerInterface
{
    public function __construct(private Timing $timing)
    {
    }

    /**
     * @return Iterator<ReflectionClass<object>>
     *
     * @throws Exception
     */
    public function getUsedClasses(string $projectRoot): Iterator
    {
        $this->timing->start(self::class);

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
     * @return string[]
     *
     * @throws JsonException
     * @throws Exception
     */
    private function getAutoloadNamespaces(string $projectRoot): array
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

        $composerData = json_decode($composerJsonData, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($composerData['autoload'])) {
            return [];
        }

        $autoload = $composerData['autoload'];

        if (!isset($autoload['psr-4'])) {
            return [];
        }

        return $autoload['psr-4'];
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
     */
    private function tryGetReflectionClass(string $composerNamespace, string $mappedDirectory, string $file): ?ReflectionClass
    {
        $actualFilePath = str_replace($mappedDirectory . DIRECTORY_SEPARATOR, '', $file);
        $className = rtrim($actualFilePath, '.php');
        $namespace = str_replace('/', '\\', $className);

        if (class_exists($namespace) || interface_exists($namespace)) {
            try {
                return new ReflectionClass($namespace);
            } catch (ReflectionException) {
                return null;
            }
        } else {
            $namespace = $composerNamespace . $namespace;

            if (class_exists($namespace) || interface_exists($namespace)) {
                try {
                    return new ReflectionClass($namespace);
                } catch (ReflectionException) {
                    return null;
                }
            }
        }

        return null;
    }
}
