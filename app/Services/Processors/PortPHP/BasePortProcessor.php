<?php

declare(strict_types=1);

namespace App\Services\Processors\PortPHP;

use App\Contracts\Processors\ProcessorInterface;
use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Data\Processors\ProcessorResultData;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Port\Steps\StepAggregator;
use Port\Writer\ArrayWriter;

/**
 * Base PortPHP Processor
 *
 * Abstract base class for processors that use PortPHP for ETL operations.
 * Wraps PortPHP's workflow system within Stash's processor interface.
 *
 * PortPHP provides:
 * - Readers: CSV, Excel, Doctrine, arrays
 * - Writers: CSV, Doctrine, arrays
 * - Converters: DateTime, charset, mapping
 * - Filters: Validation, date thresholds
 *
 * Usage: Extend this class and implement configureWorkflow()
 */
abstract class BasePortProcessor implements ProcessorInterface
{
    /**
     * Configure the PortPHP workflow with readers, writers, converters, and filters.
     *
     * @param  Document  $document  The document being processed
     * @param  ProcessorConfigData  $config  Processor configuration
     * @param  ProcessorContextData  $context  Processing context
     * @return StepAggregator The configured PortPHP workflow
     */
    abstract protected function configureWorkflow(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): StepAggregator;

    /**
     * Extract output data from the PortPHP result.
     *
     * @param  \Port\Result  $portResult  The PortPHP workflow result
     * @param  ArrayWriter  $arrayWriter  The array writer used to collect data
     * @return array Output data for ProcessorResultData
     */
    abstract protected function extractOutputData(\Port\Result $portResult, ArrayWriter $arrayWriter): array;

    /**
     * Handle document processing using PortPHP workflow.
     */
    public function handle(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): ProcessorResultData {
        try {
            // 1. Configure the PortPHP workflow (implemented by subclass)
            $workflow = $this->configureWorkflow($document, $config, $context);

            // 2. Add ArrayWriter to collect data for output_data
            $arrayWriter = new ArrayWriter();
            $workflow->addWriter($arrayWriter);

            // 3. Execute PortPHP workflow
            $startTime = microtime(true);
            $portResult = $workflow->process();
            $duration = microtime(true) - $startTime;

            // 4. Extract output data (implemented by subclass)
            $outputData = $this->extractOutputData($portResult, $arrayWriter);

            // 5. Generate artifact files if needed
            $artifactFiles = $this->generateArtifacts($outputData, $document, $config);

            // 6. Build metadata
            $metadata = [
                'port_workflow_name' => $portResult->getName(),
                'duration_seconds' => round($duration, 3),
                'rows_read' => $portResult->getTotalProcessedCount(),
                'rows_succeeded' => $portResult->getSuccessCount(),
                'rows_failed' => $portResult->getErrorCount(),
                'has_errors' => $portResult->hasErrors(),
            ];

            // Add error details if any
            if ($portResult->hasErrors()) {
                $metadata['errors'] = array_map(function ($exception) {
                    return [
                        'message' => $exception->getMessage(),
                        'line' => $exception->getLine(),
                    ];
                }, $portResult->getExceptions());
            }

            // 7. Return Stash ProcessorResultData
            return new ProcessorResultData(
                success: !$portResult->hasErrors(),
                output: $outputData,
                error: $portResult->hasErrors() ? 'PortPHP workflow completed with errors' : null,
                metadata: $metadata,
                artifactFiles: $artifactFiles
            );
        } catch (\Throwable $e) {
            return new ProcessorResultData(
                success: false,
                output: [],
                error: 'PortPHP processing failed: '.$e->getMessage(),
                metadata: [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Generate artifact files from processed data.
     *
     * Override this method to customize artifact generation.
     *
     * @param  array  $outputData  The output data
     * @param  Document  $document  The source document
     * @param  ProcessorConfigData  $config  Processor configuration
     * @return array Array of collection => file_path for artifacts
     */
    protected function generateArtifacts(
        array $outputData,
        Document $document,
        ProcessorConfigData $config
    ): array {
        // Default: no artifacts
        // Subclasses can override to generate CSV, JSON, or Excel exports
        return [];
    }

    /**
     * Get the full file path for the document.
     */
    protected function getDocumentPath(Document $document): string
    {
        return Storage::disk($document->storage_disk)->path($document->storage_path);
    }

    /**
     * Create a temporary file for artifact generation.
     *
     * @param  string  $extension  File extension (e.g., 'json', 'csv')
     * @return string Path to temporary file
     */
    protected function createTempFile(string $extension): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = uniqid('stash_artifact_', true).'.'.$extension;

        return $tempDir.'/'.$filename;
    }

    /**
     * Export data to JSON file.
     *
     * @param  array  $data  Data to export
     * @return string Path to JSON file
     */
    protected function exportToJson(array $data): string
    {
        $path = $this->createTempFile('json');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * Check if this processor can process the given document.
     */
    public function canProcess(Document $document): bool
    {
        // Default: check if file exists
        return Storage::disk($document->storage_disk)->exists($document->storage_path);
    }

    /**
     * Get the processor category.
     */
    public function getCategory(): string
    {
        return 'transformation';
    }

    /**
     * Get the output schema for validation.
     */
    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rows_imported' => ['type' => 'integer'],
                'rows_failed' => ['type' => 'integer'],
                'data' => ['type' => 'array'],
            ],
            'required' => ['rows_imported', 'rows_failed'],
        ];
    }
}
