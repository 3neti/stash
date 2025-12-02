<?php

declare(strict_types=1);

namespace App\Workflows\Activities;

use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Pipeline\ProcessorRegistry;
use App\Services\Tenancy\TenancyService;
use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use Workflow\Activity;

/**
 * ExtractionActivity
 *
 * Laravel Workflow Activity that wraps the existing Extraction processor.
 */
class ExtractionActivity extends Activity
{
    /**
     * Maximum number of retry attempts.
     */
    public $tries = 3;

    /**
     * Timeout in seconds (3 minutes for extraction).
     */
    public $timeout = 180;
    /**
     * Execute extraction processing for a document.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param array $ocrResult OCR results from previous activity
     * @param array $classificationResult Classification results from previous activity
     * @param string $tenantId The Tenant ULID
     * @return array Extraction results (extracted fields, etc.)
     */
    public function execute(
        string $documentJobId,
        array $ocrResult,
        array $classificationResult,
        string $tenantId
    ): array {
        // Step 1: Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // Step 2: Load DocumentJob from tenant database
        $documentJob = DocumentJob::findOrFail($documentJobId);
        $document = $documentJob->document;

        // Step 3: Get processor from registry
        $registry = app(ProcessorRegistry::class);
        $processor = $registry->get('extraction');

        // Step 4: Get processor config from pipeline instance
        $processorConfig = collect($documentJob->pipeline_instance['processors'] ?? [])
            ->firstWhere('id', 'extraction')
            ?? collect($documentJob->pipeline_instance['processors'] ?? [])
                ->firstWhere('type', 'extraction');

        if (!$processorConfig) {
            throw new \RuntimeException('Extraction processor not found in pipeline config');
        }

        $config = ProcessorConfigData::from($processorConfig);

        // Step 5: Create context with previous outputs
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 2,
            previousOutputs: [
                'ocr' => $ocrResult,
                'classification' => $classificationResult,
            ]
        );

        // Step 6: Execute processor
        $result = $processor->handle($document, $config, $context);

        if (!$result->success) {
            throw new \RuntimeException($result->error ?? 'Extraction processing failed');
        }

        // Step 7: Update document metadata
        $metadata = $document->metadata ?? [];
        $metadata['extraction_output'] = $result->output;
        $document->update(['metadata' => $metadata]);

        // Return results for next activity
        return $result->output;
    }
}
