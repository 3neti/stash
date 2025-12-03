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
use App\Workflows\Activities\Concerns\HandlesProcessorArtifacts;
use Workflow\Activity;

/**
 * ValidationActivity
 *
 * Laravel Workflow Activity that wraps the existing Validation processor.
 */
class ValidationActivity extends Activity
{
    use HandlesProcessorArtifacts;

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
     * @param  string  $documentJobId  The DocumentJob ULID
     * @param  array  $extractionResult  Extraction results from previous activity
     * @param  string  $tenantId  The Tenant ULID
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

        // Step 3: Get fourth processor from pipeline (Validation is typically fourth)
        $processorConfigs = $documentJob->pipeline_instance['processors'] ?? [];

        if (count($processorConfigs) < 4) {
            throw new \RuntimeException('Validation processor not configured in pipeline');
        }

        // Get fourth processor config (Validation is fourth in pipeline)
        $processorConfig = $processorConfigs[3];
        $processorId = $processorConfig['id'] ?? null;

        if (! $processorId) {
            throw new \RuntimeException('Processor ID missing in pipeline config');
        }

        // Step 4: Load Processor model and get from registry
        $processorModel = \App\Models\Processor::find($processorId);
        if (! $processorModel) {
            throw new \RuntimeException("Processor not found: {$processorId}");
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

        // Step 5: Create context with extraction results
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 3,
            previousOutputs: [
                'extraction' => $extractionResult,
            ]
        );

        // Step 6: Create ProcessorExecution record for tracking
        $execution = ProcessorExecution::create([
            'job_id' => $documentJob->id,
            'processor_id' => $processorModel->id,
            'input_data' => ['extraction_result' => $extractionResult],
            'config' => $config->toArray(),
        ]);
        $execution->start();

        // Step 7: Execute processor
        try {
            $result = $processor->handle($document, $config, $context);

            if (! $result->success) {
                $error = $result->error ?? 'Validation processing failed';
                $execution->fail($error);
                throw new \RuntimeException($error);
            }

            // Mark execution as completed
            $execution->complete(
                output: $result->output,
                tokensUsed: (int) ($result->output['tokens_used'] ?? 0),
                costCredits: (int) ($result->output['cost_credits'] ?? 0)
            );

            // Attach any artifact files from processor result
            $this->attachResultArtifacts($execution, $result, $document, 'validation');

            // Fire event for real-time monitoring
            event(new ProcessorExecutionCompleted($execution, $documentJob));
        } catch (\Throwable $e) {
            if (! $execution->isCompleted()) {
                $execution->fail($e->getMessage());
            }
            throw $e;
        }

        // Step 8: Update document metadata
        $metadata = $document->metadata ?? [];
        $metadata['validation_output'] = $result->output;
        $document->update(['metadata' => $metadata]);

        // Return results (final activity)
        return $result->output;
    }
}
