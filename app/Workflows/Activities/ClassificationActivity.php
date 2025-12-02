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
 * ClassificationActivity
 *
 * Laravel Workflow Activity that wraps the existing Classification processor.
 */
class ClassificationActivity extends Activity
{
    /**
     * Maximum number of retry attempts.
     */
    public $tries = 3;

    /**
     * Timeout in seconds (2 minutes for classification).
     */
    public $timeout = 120;
    /**
     * Execute classification processing for a document.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param array $ocrResult OCR results from previous activity
     * @param string $tenantId The Tenant ULID
     * @return array Classification results (category, confidence, etc.)
     */
    public function execute(string $documentJobId, array $ocrResult, string $tenantId): array
    {
        // Step 1: Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // Step 2: Load DocumentJob from tenant database
        $documentJob = DocumentJob::findOrFail($documentJobId);
        $document = $documentJob->document;

        // Step 3: Get processor from registry
        $registry = app(ProcessorRegistry::class);
        $processor = $registry->get('classification');

        // Step 4: Get processor config from pipeline instance
        $processorConfig = collect($documentJob->pipeline_instance['processors'] ?? [])
            ->firstWhere('id', 'classification')
            ?? collect($documentJob->pipeline_instance['processors'] ?? [])
                ->firstWhere('type', 'classification');

        if (!$processorConfig) {
            throw new \RuntimeException('Classification processor not found in pipeline config');
        }

        $config = ProcessorConfigData::from($processorConfig);

        // Step 5: Create context with OCR results as previous output
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 1,
            previousOutputs: ['ocr' => $ocrResult]
        );

        // Step 6: Execute processor
        $result = $processor->handle($document, $config, $context);

        if (!$result->success) {
            throw new \RuntimeException($result->error ?? 'Classification processing failed');
        }

        // Step 7: Update document metadata
        $metadata = $document->metadata ?? [];
        $metadata['classification_output'] = $result->output;
        $document->update(['metadata' => $metadata]);

        // Return results for next activity
        return $result->output;
    }
}
