<?php

declare(strict_types=1);

namespace Palacios\Framework\Routing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use SplFileInfo;

final readonly class ControllerDiscovery
{
    public function __construct(private string $basePath) {}

    /**
     * @param list<array{directory: string, namespace: string}> $sources
     * @return list<class-string>
     */
    public function discover(array $sources): array
    {
        $controllers = [];

        foreach ($sources as $source) {
            $directory = $this->basePath . '/' . $source['directory'];
            if (!is_dir($directory)) {
                throw new RuntimeException("Diretório de controllers não encontrado: {$directory}");
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
                $class = $source['namespace'] . '\\' . str_replace('/', '\\', $relative);

                if (!class_exists($class)) {
                    throw new RuntimeException("Controller não encontrado no autoload: {$class}");
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isInstantiable()) {
                    /** @var class-string $class */
                    $controllers[] = $class;
                }
            }
        }

        sort($controllers);
        return array_values(array_unique($controllers));
    }
}
