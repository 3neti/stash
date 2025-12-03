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
 * ClassificationActivity
 *
 * Laravel Workflow Activity that wraps the existing Classification processor.
 */
class ClassificationActivity extends Activity
{
    use HandlesProcessorArtifacts;

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
     * @param  string  $documentJobId  The DocumentJob ULID
     * @param  array  $ocrResult  OCR results from previous activity
     * @param  string  $tenantId  The Tenant ULID
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

        // Step 3: Get second processor from pipeline (Classification is typically second)
        $processorConfigs = $documentJob->pipeline_instance['processors'] ?? [];

        if (count($processorConfigs) < 2) {
            throw new \RuntimeException('Classification processor not configured in pipeline');
        }

        // Get second processor config (Classification is second in pipeline)
        $processorConfig = $processorConfigs[1];
        $processorId = $processorConfig['id'] ?? null;

        // If processor ID is null, skip this step (return empty result)
        if (! $processorId) {
            return [
                'skipped' => true,
                'reason' => 'No classification processor configured for this campaign',
            ];
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

        // Step 5: Create context with OCR results as previous output
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: 1,
            previousOutputs: ['ocr' => $ocrResult]
        );

        // Step 6: Create ProcessorExecution record for tracking
        $execution = ProcessorExecution::create([
            'job_id' => $documentJob->id,
            'processor_id' => $processorModel->id,
            'input_data' => ['ocr_result' => $ocrResult],
            'config' => $config->toArray(),
        ]);
        $execution->start();

        // Step 7: Execute processor
        try {
            $result = $processor->handle($document, $config, $context);

            if (! $result->success) {
                $error = $result->error ?? 'Classification processing failed';
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
            $this->attachResultArtifacts($execution, $result, $document, 'classification');

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
        $metadata['classification_output'] = $result->output;
        // Store category for downstream processors
        $metadata['category'] = $result->output['category'] ?? 'unknown';
        $document->update(['metadata' => $metadata]);

        // Return results for next activity
        return $result->output;
    }
}
