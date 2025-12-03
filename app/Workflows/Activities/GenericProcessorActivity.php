<?php

declare(strict_types=1);

namespace App\Workflows\Activities;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Events\ProcessorExecutionCompleted;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Models\Tenant;
use App\Services\Pipeline\ProcessorRegistry;
use App\Services\Tenancy\TenancyService;
use App\Workflows\Activities\Concerns\HandlesProcessorArtifacts;
use Workflow\Activity;
use Workflow\Exceptions\NonRetryableException;

/**
 * GenericProcessorActivity
 *
 * A dynamic Laravel Workflow Activity that can execute ANY processor from a campaign's pipeline.
 * This replaces the hardcoded activities (OcrActivity, ClassificationActivity, etc.) with a single
 * flexible activity that reads the processor configuration at runtime.
 *
 * Activities are automatically:
 * - Retried on failure (configurable)
 * - Executed asynchronously on queue workers
 * - Isolated from workflow state (workflow can resume even if activity fails)
 */
class GenericProcessorActivity extends Activity
{
    use HandlesProcessorArtifacts;

    /**
     * Maximum number of retry attempts.
     */
    public $tries = 5;

    /**
     * Timeout in seconds (5 minutes for most operations).
     */
    public $timeout = 300;

    /**
     * Execute a processor at the specified index in the pipeline.
     *
     * @param  string  $documentJobId  The DocumentJob ULID
     * @param  int  $processorIndex  Index in the pipeline_instance['processors'] array
     * @param  array  $previousResults  Results from previous processors
     * @param  string  $tenantId  The Tenant ULID
     * @return array Processor results or skip indicator
     */
    public function execute(
        string $documentJobId,
        int $processorIndex,
        array $previousResults,
        string $tenantId
    ): array {
        // Step 1: Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // Step 2: Load DocumentJob from tenant database
        $documentJob = DocumentJob::findOrFail($documentJobId);
        $document = $documentJob->document;

        // Step 3: Get processor config at specified index
        $processorConfigs = $documentJob->pipeline_instance['processors'] ?? [];

        if ($processorIndex >= count($processorConfigs)) {
            throw new NonRetryableException("Processor index {$processorIndex} out of bounds");
        }

        $processorConfig = $processorConfigs[$processorIndex];
        $processorId = $processorConfig['id'] ?? null;

        // Handle null processor (skip this step)
        if (! $processorId) {
            return [
                'skipped' => true,
                'reason' => 'No processor configured at this index',
                'index' => $processorIndex,
            ];
        }

        // Step 4: Load Processor model
        $processorModel = Processor::find($processorId);
        if (! $processorModel) {
            throw new NonRetryableException("Processor not found: {$processorId}");
        }

        // Step 5: Get processor implementation from registry
        $registry = app(ProcessorRegistry::class);

        // Register processor if not already registered
        if (! $registry->has($processorModel->slug) && $processorModel->class_name) {
            $registry->register($processorModel->slug, $processorModel->class_name);
        }

        $processor = $registry->get($processorModel->slug);

        // Create ProcessorConfigData from processor config
        $config = ProcessorConfigData::from($processorConfig);

        // Step 6: Create context with previous results
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: $processorIndex,
            previousOutputs: $previousResults
        );

        // Step 7: Create ProcessorExecution record for tracking
        $execution = ProcessorExecution::create([
            'job_id' => $documentJob->id,
            'processor_id' => $processorModel->id,
            'input_data' => [
                'document_id' => $document->id,
                'processor_index' => $processorIndex,
                'previous_results_count' => count($previousResults),
            ],
            'config' => $config->toArray(),
        ]);
        $execution->start();

        // Step 8: Execute processor
        try {
            $result = $processor->handle($document, $config, $context);

            if (! $result->success) {
                $error = $result->error ?? 'Processor execution failed';
                $execution->fail($error);

                // Check if this is a permanent failure vs temporary
                if (str_contains($error, 'unsupported') || 
                    str_contains($error, 'invalid file') ||
                    str_contains($error, 'not found')) {
                    // Don't retry for permanent errors
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

            // Attach any artifact files from processor result
            $processorCategory = $processorModel->category ?? 'generic';
            $this->attachResultArtifacts($execution, $result, $document, $processorCategory);

            // Fire event for real-time monitoring
            event(new ProcessorExecutionCompleted($execution, $documentJob));
        } catch (\Throwable $e) {
            // Avoid invalid state transition if "complete()" partially succeeded
            if (! $execution->isCompleted()) {
                $execution->fail($e->getMessage());
            }
            throw $e;
        }

        // Step 9: Update document metadata (store output for downstream processors)
        $metadata = $document->metadata ?? [];
        $processorKey = $processorModel->slug ?? "processor_{$processorIndex}";
        $metadata[$processorKey . '_output'] = $result->output;
        
        // Also store in a flat array for easy access
        if (! isset($metadata['processor_outputs'])) {
            $metadata['processor_outputs'] = [];
        }
        $metadata['processor_outputs'][$processorIndex] = [
            'processor_slug' => $processorModel->slug,
            'processor_name' => $processorModel->name,
            'output' => $result->output,
        ];
        
        $document->update(['metadata' => $metadata]);

        // Return results for next processor
        return $result->output;
    }
}
