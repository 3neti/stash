<?php

declare(strict_types=1);

namespace App\Workflows\Activities;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Events\ProcessorExecutionCompleted;
use App\Models\DocumentJob;
use App\Models\ProcessorExecution;
use App\Models\Tenant;
use App\Services\Pipeline\ProcessorRegistry;
use App\Services\Tenancy\TenancyService;
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
     * @param  string  $documentJobId  The DocumentJob ULID
     * @param  string  $tenantId  The Tenant ULID
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

        // Step 3: Get first processor from pipeline (OCR is typically first)
        $processorConfigs = $documentJob->pipeline_instance['processors'] ?? [];

        if (empty($processorConfigs)) {
            throw new NonRetryableException('No processors configured in pipeline');
        }

        // Get first processor config (OCR is first in pipeline)
        $processorConfig = $processorConfigs[0];
        $processorId = $processorConfig['id'] ?? null;

        if (! $processorId) {
            throw new NonRetryableException('Processor ID missing in pipeline config');
        }

        // Step 4: Load Processor model and get from registry
        $processorModel = \App\Models\Processor::find($processorId);
        if (! $processorModel) {
            throw new NonRetryableException("Processor not found: {$processorId}");
        }

        // Get processor implementation from registry using slug
        $registry = app(ProcessorRegistry::class);

        // Register processor if not already registered
        if (! $registry->has($processorModel->slug) && $processorModel->class_name) {
            $registry->register($processorModel->slug, $processorModel->class_name);
        }

        $processor = $registry->get($processorModel->slug);

        // Create ProcessorConfigData from processor config
        $config = ProcessorConfigData::from($processorConfig);

        // Step 5: Create context
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 0,
            previousOutputs: []
        );

        // Step 6: Create ProcessorExecution record for tracking
        $execution = ProcessorExecution::create([
            'job_id' => $documentJob->id,
            'processor_id' => $processorModel->id,
            'input_data' => ['document_id' => $document->id],
            'config' => $config->toArray(),
        ]);
        $execution->start();

        // Step 7: Execute processor (existing implementation, no changes)
        try {
            $result = $processor->handle($document, $config, $context);

            if (! $result->success) {
                $error = $result->error ?? 'OCR processing failed';
                $execution->fail($error);

                // Check if this is a permanent failure (e.g., unsupported file format)
                // vs temporary (e.g., API timeout) - throw appropriate exception
                if (str_contains($error, 'unsupported') || str_contains($error, 'invalid file')) {
                    // Don't retry for unsupported files
                    throw new NonRetryableException($error);
                }

                // Temporary failures get retried automatically (up to $tries limit)
                throw new \RuntimeException($error);
            }

            // Mark execution as completed
            $execution->complete(
                output: $result->output,
                tokensUsed: (int) ($result->output['tokens_used'] ?? 0),
                costCredits: (int) ($result->output['cost_credits'] ?? 0)
            );

            // Fire event for real-time monitoring
            event(new ProcessorExecutionCompleted($execution, $documentJob));
        } catch (\Throwable $e) {
            // Avoid invalid state transition if "complete()" partially succeeded
            if (! $execution->isCompleted()) {
                $execution->fail($e->getMessage());
            }
            throw $e;
        }

        // Step 8: Update document metadata (optional - could be done in separate activity)
        $metadata = $document->metadata ?? [];
        $metadata['ocr_output'] = $result->output;
        // Store extracted text for downstream processors
        $metadata['extracted_text'] = $result->output['text'] ?? '';
        $document->update(['metadata' => $metadata]);

        // Return results for next activity
        return $result->output;
    }
}
