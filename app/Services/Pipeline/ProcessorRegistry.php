<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Contracts\Processors\ProcessorInterface;
use App\Exceptions\ProcessorException;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Processor Registry
 *
 * Manages registration and instantiation of processors.
 */
class ProcessorRegistry
{
    /**
     * Registered processors: class name indexed by ID.
     */
    protected array $processors = [];

    public function __construct(
        protected Container $container
    ) {}

    /**
     * Register a processor manually.
     */
    public function register(string $id, string $className): void
    {
        if (! class_exists($className)) {
            throw new InvalidArgumentException("Processor class '{$className}' does not exist");
        }

        if (! is_subclass_of($className, ProcessorInterface::class)) {
            throw new InvalidArgumentException("Processor '{$className}' must implement ProcessorInterface");
        }

        $this->processors[$id] = $className;
    }

    /**
     * Check if a processor is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->processors[$id]);
    }

    /**
     * Get a processor instance by ID.
     */
    public function get(string $id): ProcessorInterface
    {
        if (! $this->has($id)) {
            throw ProcessorException::processingFailed($id, 'Processor not registered');
        }

        $className = $this->processors[$id];

        return $this->container->make($className);
    }

    /**
     * Get all registered processor IDs.
     */
    public function getRegisteredIds(): array
    {
        return array_keys($this->processors);
    }

    /**
     * Auto-discover processors in the app/Processors directory.
     * This method can be called during application boot.
     */
    public function discover(): void
    {
        $processorsPath = app_path('Processors');

        if (! is_dir($processorsPath)) {
            return;
        }

        $files = glob($processorsPath.'/*.php');

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if ($className &&
                class_exists($className) &&
                is_subclass_of($className, ProcessorInterface::class) &&
                ! str_contains($className, 'Abstract')
            ) {
                // Convert class name to lowercase ID: OcrProcessor -> ocr
                $baseName = class_basename($className);
                // Remove "Processor" suffix and convert to lowercase
                $id = strtolower(str_replace('Processor', '', $baseName));
                $this->register($id, $className);
            }
        }
    }

    /**
     * Extract class name from file path.
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $relativePath = str_replace(app_path(), '', $file);
        $relativePath = ltrim($relativePath, '/');
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        return 'App\\'.$relativePath;
    }
}
