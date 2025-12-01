<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Data\Processors\ProcessorResult;
use App\Events\DocumentJobCreated;
use App\Events\DocumentProcessingStageCompleted;
use App\Events\ProcessorExecutionCompleted;
use App\Events\ProcessorExecutionFailed;
use App\Events\ProcessorExecutionStarted;
use App\Jobs\Pipeline\ProcessDocumentJob;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DocumentProcessingPipeline orchestrates document processing through a processor graph.
 *
 * Responsible for:
 * - Creating DocumentJob from Document and Campaign pipeline config
 * - Executing processors sequentially
 * - Tracking ProcessorExecution records
 * - Handling processor results and state transitions
 * - Firing observable events at each stage
 */
class DocumentProcessingPipeline
{
    public function __construct(
        private ProcessorRegistry $registry,
    ) {}

    /**
     * Process a document through the campaign's pipeline.
     *
     * Creates a DocumentJob snapshotting the pipeline configuration,
     * then dispatches it to the queue for execution.
     *
     * @return DocumentJob The created job
     */
    public function process(Document $document, Campaign $campaign): DocumentJob
    {
        Log::debug('[Pipeline] Processing document', [
            'document_id' => $document->id,
            'campaign_id' => $campaign->id,
        ]);

        // Create DocumentJob with snapshot of pipeline configuration
        $job = DocumentJob::create([
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
            'pipeline_instance' => $campaign->pipeline_config,
            'current_processor_index' => 0,
            'queue_name' => 'default',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
        Log::debug('[Pipeline] DocumentJob created', ['job_id' => $job->id, 'uuid' => $job->uuid]);

        // Fire event that job was created
        event(new DocumentJobCreated($job, $document, $campaign));

        // Dispatch job to queue for processing
        Log::debug('[Pipeline] Dispatching job to queue');
        // Include tenant ID in job payload for middleware to use during bootstrap
        $tenantId = TenantContext::current()?->id;
        Log::debug('[Pipeline] Job will be dispatched with tenantId', ['tenant_id' => $tenantId]);
        ProcessDocumentJob::dispatch($job->id, $tenantId);

        return $job;
    }

    /**
     * Execute the next processor stage in a DocumentJob.
     *
     * Fetches the current processor from the pipeline configuration,
     * executes it, and handles the result (success/failure/retry).
     *
     * @return bool true if processing should continue (more stages), false if complete or failed
     */
    public function executeNextStage(DocumentJob $job): bool
    {
        $pipeline = $job->pipeline_instance;
        $processors = $pipeline['processors'] ?? [];
        $currentIndex = $job->current_processor_index;

        // Check if all stages completed
        if ($currentIndex >= count($processors)) {
            $this->completeProcessing($job);
            return false;
        }

        // Get current processor config
        $processorConfig = $processors[$currentIndex];
        $processorId = $processorConfig['id'] ?? $processorConfig['type'] ?? null;

        if (! $processorId) {
            $this->failProcessing($job, 'Invalid processor configuration: missing id/type');
            return false;
        }

        // Get the processor implementation from registry
        try {
            $processor = $this->registry->get($processorId);
        } catch (\RuntimeException $e) {
            $this->failProcessing($job, "Processor not found: {$processorId}");
            return false;
        }

        // Look up the Processor model record (database)
        $processorModel = Processor::where('category', $processorId)->first();
        if (!$processorModel) {
            $this->failProcessing($job, "Processor '{$processorId}' not found in database");
            return false;
        }

        // Create ProcessorExecution record with actual Processor model ID
        $execution = ProcessorExecution::create([
            'job_id' => $job->id,
            'processor_id' => $processorModel->id,  // Use Processor model ULID, not type string
            'input_data' => $job->document->metadata ?? [],
            'config' => $processorConfig['config'] ?? [],
            'status' => 'pending',
        ]);

        // Fire event that execution started
        event(new ProcessorExecutionStarted($execution, $job));

        // Execute processor
        try {
            $result = $processor->handle(
                $job->document,
                $processorConfig['config'] ?? [],
                [
                    'job_id' => $job->id,
                    'processor_index' => $currentIndex,
                    'previous_outputs' => $job->document->metadata['processor_outputs'] ?? [],
                ]
            );

            // Handle result
            return $this->handleStageResult($job, $execution, $result, $processorConfig);
        } catch (\Throwable $e) {
            // Processor threw exception
            $result = ProcessorResult::failed('Processor exception: '.$e->getMessage());
            return $this->handleStageResult($job, $execution, $result, $processorConfig);
        }
    }

    /**
     * Handle the result of a processor execution.
     *
     * Updates ProcessorExecution, advances job state, and either continues or fails.
     *
     * @return bool true if processing should continue, false if complete or failed
     */
    private function handleStageResult(DocumentJob $job, ProcessorExecution $execution, ProcessorResult $result, array $processorConfig): bool
    {
        if ($result->isSuccess()) {
            // Update execution
            $execution->update([
                'status' => 'completed',
                'output_data' => $result->output,
                'duration_ms' => $result->metadata['duration_ms'] ?? null,
                'tokens_used' => $result->tokensUsed,
                'cost_credits' => $result->costCredits,
                'completed_at' => now(),
            ]);

            // Fire event
            event(new ProcessorExecutionCompleted($execution, $job));

            // Store output in document metadata for next stages
            $metadata = $job->document->metadata ?? [];
            if (! isset($metadata['processor_outputs'])) {
                $metadata['processor_outputs'] = [];
            }
            $metadata['processor_outputs'][] = [
                'processor' => $processorConfig['id'] ?? $processorConfig['type'],
                'output' => $result->output,
            ];
            $job->document->update(['metadata' => $metadata]);

            // Fire stage completed event
            event(new DocumentProcessingStageCompleted($job));

            // Advance to next processor
            $job->advanceProcessor();
            $job->save();

            // Continue with next stage
            return true;
        } else {
            // Processor failed
            $execution->update([
                'status' => 'failed',
                'error_message' => $result->error,
                'failed_at' => now(),
            ]);

            // Fire event
            event(new ProcessorExecutionFailed($execution, $job));

            // Check if we can retry
            if ($job->canRetry()) {
                $job->incrementAttempts();
                $job->save();

                // Will be re-queued
                return true;
            } else {
                // No more retries
                $this->failProcessing($job, $result->error ?? 'Unknown processor error');
                return false;
            }
        }
    }

    /**
     * Mark processing as complete.
     *
     * Updates job and document states, fires completion events.
     */
    public function completeProcessing(DocumentJob $job): void
    {
        $job->complete();
        $job->document->markCompleted();
    }

    /**
     * Mark processing as failed.
     *
     * Updates job and document states, fires failure events.
     */
    public function failProcessing(DocumentJob $job, string $error): void
    {
        $job->fail($error);
        $job->document->markFailed($error);
    }
}
