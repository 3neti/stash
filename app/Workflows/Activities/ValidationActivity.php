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
 * ValidationActivity
 *
 * Laravel Workflow Activity that wraps the existing Validation processor.
 */
class ValidationActivity extends Activity
{
    /**
     * Maximum number of retry attempts.
     */
    public $tries = 2;

    /**
     * Timeout in seconds (1 minute for validation).
     */
    public $timeout = 60;
    /**
     * Execute validation processing for a document.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param array $extractionResult Extraction results from previous activity
     * @param string $tenantId The Tenant ULID
     * @return array Validation results (is_valid, errors, etc.)
     */
    public function execute(
        string $documentJobId,
        array $extractionResult,
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
        $processor = $registry->get('validation');

        // Step 4: Get processor config from pipeline instance
        $processorConfig = collect($documentJob->pipeline_instance['processors'] ?? [])
            ->firstWhere('id', 'validation')
            ?? collect($documentJob->pipeline_instance['processors'] ?? [])
                ->firstWhere('type', 'validation');

        if (!$processorConfig) {
            throw new \RuntimeException('Validation processor not found in pipeline config');
        }

        $config = ProcessorConfigData::from($processorConfig);

        // Step 5: Create context with extraction results
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 3,
            previousOutputs: [
                'extraction' => $extractionResult,
            ]
        );

        // Step 6: Execute processor
        $result = $processor->handle($document, $config, $context);

        if (!$result->success) {
            throw new \RuntimeException($result->error ?? 'Validation processing failed');
        }

        // Step 7: Update document metadata
        $metadata = $document->metadata ?? [];
        $metadata['validation_output'] = $result->output;
        $document->update(['metadata' => $metadata]);

        // Return results (final activity)
        return $result->output;
    }
}
