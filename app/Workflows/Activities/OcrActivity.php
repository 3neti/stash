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
use Workflow\Exceptions\NonRetryableException;

/**
 * OcrActivity
 *
 * Laravel Workflow Activity that wraps the existing OCR processor.
 * Activities are the "unit of work" in Laravel Workflow - they're automatically:
 * - Retried on failure (configurable)
 * - Executed asynchronously on queue workers
 * - Isolated from workflow state (workflow can resume even if activity fails)
 *
 * NOTE: This is a proof-of-concept. Shows how to wrap existing ProcessorInterface in Activity pattern.
 */
class OcrActivity extends Activity
{
    /**
     * Maximum number of retry attempts.
     * Default is infinite, but we limit to 5 for OCR operations.
     */
    public $tries = 5;

    /**
     * Timeout in seconds (5 minutes for OCR processing).
     */
    public $timeout = 300;
    /**
     * Execute OCR processing for a document.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param string $tenantId The Tenant ULID
     * @return array OCR results (text, confidence, etc.)
     */
    public function execute(string $documentJobId, string $tenantId): array
    {
        // Step 1: Initialize tenant context
        // (In Laravel Workflow, each activity execution is isolated)
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // Step 2: Load DocumentJob from tenant database
        $documentJob = DocumentJob::findOrFail($documentJobId);
        $document = $documentJob->document;

        // Step 3: Get processor from registry (existing infrastructure)
        $registry = app(ProcessorRegistry::class);
        $processor = $registry->get('ocr');

        // Step 4: Get processor config from pipeline instance
        $processorConfig = collect($documentJob->pipeline_instance['processors'] ?? [])
            ->firstWhere('id', 'ocr')
            ?? collect($documentJob->pipeline_instance['processors'] ?? [])
                ->firstWhere('type', 'ocr');

        if (!$processorConfig) {
            // NonRetryableException prevents automatic retries for configuration errors
            // Use this for errors that won't be fixed by retrying (e.g., missing config, invalid data)
            throw new NonRetryableException('OCR processor not found in pipeline config');
        }

        $config = ProcessorConfigData::from($processorConfig);

        // Step 5: Create context
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 0,
            previousOutputs: []
        );

        // Step 6: Execute processor (existing implementation, no changes)
        $result = $processor->handle($document, $config, $context);

        if (!$result->success) {
            // Check if this is a permanent failure (e.g., unsupported file format)
            // vs temporary (e.g., API timeout) - throw appropriate exception
            $error = $result->error ?? 'OCR processing failed';
            
            if (str_contains($error, 'unsupported') || str_contains($error, 'invalid file')) {
                // Don't retry for unsupported files
                throw new NonRetryableException($error);
            }
            
            // Temporary failures get retried automatically (up to $tries limit)
            throw new \RuntimeException($error);
        }

        // Step 7: Update document metadata (optional - could be done in separate activity)
        $metadata = $document->metadata ?? [];
        $metadata['ocr_output'] = $result->output;
        $document->update(['metadata' => $metadata]);

        // Return results for next activity
        return $result->output;
    }
}
