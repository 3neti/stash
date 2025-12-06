<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Events\DocumentJobCreated;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\PipelineProgress;
use App\Tenancy\TenantContext;
use App\Workflows\DocumentProcessingWorkflow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

/**
 * DocumentProcessingPipeline orchestrates document processing through Laravel Workflow.
 *
 * Responsible for:
 * - Creating DocumentJob from Document and Campaign pipeline config
 * - Starting Laravel Workflow for durable async execution
 * - Tracking progress
 * - Firing observable events
 */
class DocumentProcessingPipeline
{

    /**
     * Process a document through the campaign's pipeline using Laravel Workflow.
     *
     * Creates a DocumentJob snapshotting the pipeline configuration,
     * then starts a durable workflow for execution.
     *
     * @return DocumentJob The created job
     */
    public function process(Document $document, Campaign $campaign): DocumentJob
    {
        Log::info('[Pipeline] Processing document via Laravel Workflow', [
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

        // Create PipelineProgress record
        $stageCount = count($campaign->pipeline_config['processors'] ?? []);
        PipelineProgress::create([
            'job_id' => $job->id,
            'stage_count' => $stageCount,
            'completed_stages' => 0,
            'percentage_complete' => 0,
            'current_stage' => null,
            'status' => 'pending',
        ]);

        // Fire event that job was created
        event(new DocumentJobCreated($job, $document, $campaign));

        // Get tenant ID from current context for workflow
        $tenantId = TenantContext::current()?->id;

        // Start Laravel Workflow
        Log::info('[Pipeline] Starting Laravel Workflow', [
            'job_id' => $job->id,
            'tenant_id' => $tenantId,
        ]);

        $workflow = WorkflowStub::make(DocumentProcessingWorkflow::class);
        $workflow->start($job->id, $tenantId);

        return $job;
    }

    /**
     * @deprecated Legacy method - workflows handle execution now.
     * Kept temporarily for any existing references. Will be removed.
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

        // Update progress to mark as processing
        $progress = PipelineProgress::where('job_id', $job->id)->first();
        if ($progress && $progress->status === 'pending') {
            $progress->update(['status' => 'processing']);
        }

        // Get current processor config
        $processorConfig = $processors[$currentIndex];
        // Support both 'id' (ULID) and 'slug'/'type' (string identifier)
        $processorId = $processorConfig['id'] ?? $processorConfig['slug'] ?? $processorConfig['type'] ?? null;

        if (! $processorId) {
            $this->failProcessing($job, 'Invalid processor configuration: missing id/slug/type');
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
        // If processorId is a ULID, look it up by ID; otherwise by slug
        if (strlen($processorId) === 26) {
            // Likely a ULID (26 chars)
            $processorModel = Processor::find($processorId);
        } else {
            // Lookup by slug
            $processorModel = Processor::where('slug', $processorId)->first();
        }
        
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

        // Call hooks before execution
        if ($this->hookManager) {
            $this->hookManager->beforeExecution($execution);
        }

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
            return $this->handleStageResult($job, $execution, $result, $processorConfig, $processor);
        } catch (\Throwable $e) {
            // Call failure hook
            if ($this->hookManager) {
                $this->hookManager->onFailure($execution, $e);
            }

            // Processor threw exception
            $result = ProcessorResult::failed('Processor exception: '.$e->getMessage());
            return $this->handleStageResult($job, $execution, $result, $processorConfig, $processor);
        }
    }

    /**
     * @deprecated Legacy method - workflows handle execution now.
     */
    private function handleStageResult(DocumentJob $job, ProcessorExecution $execution, ProcessorResult $result, array $processorConfig, ?object $processor = null): bool
    {
        if ($result->isSuccess()) {
            // Validate output if processor defines a schema
            if ($processor && method_exists($processor, 'getOutputSchema')) {
                $schema = $processor->getOutputSchema();
                if ($schema) {
                    $validator = new JsonSchemaValidator();
                    if (!$validator->isValid($result->output, $schema)) {
                        $validationResult = $validator->validate($result->output, $schema);
                        $errorMessage = 'Output validation failed: ' . json_encode($validationResult['errors']);
                        Log::error('[Pipeline] Output validation failed', [
                            'execution_id' => $execution->id,
                            'errors' => $validationResult['errors'],
                        ]);
                        // Fail the entire job on validation error
                        $this->failProcessing($job, $errorMessage);
                        return false;
                    }
                }
            }

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

            // Call hooks after execution
            if ($this->hookManager) {
                $this->hookManager->afterExecution($execution, $result->output);
            }

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

            // Update progress
            $progress = PipelineProgress::where('job_id', $job->id)->first();
            if ($progress) {
                $nextIndex = $job->current_processor_index;
                $totalStages = count($job->pipeline_instance['processors'] ?? []);
                $processorName = $job->pipeline_instance['processors'][$currentIndex]['id']
                    ?? $job->pipeline_instance['processors'][$currentIndex]['type']
                    ?? "Stage {$currentIndex}";
                $progress->updateProgress($nextIndex, $totalStages, $processorName, 'processing');
            }

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

            // Call failure hook with synthetic exception
            if ($this->hookManager) {
                $this->hookManager->onFailure($execution, new \RuntimeException($result->error ?? 'Processor failed'));
            }

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
     * @deprecated Legacy method - workflow listeners handle completion now.
     */
    public function completeProcessing(DocumentJob $job): void
    {
        // Update progress to completed
        $progress = PipelineProgress::where('job_id', $job->id)->first();
        if ($progress) {
            $totalStages = count($job->pipeline_instance['processors'] ?? []);
            $progress->updateProgress($totalStages, $totalStages, 'Completed', 'completed');
        }

        $job->complete();
        $job->document->markCompleted();
    }

    /**
     * @deprecated Legacy method - workflow listeners handle failures now.
     */
    public function failProcessing(DocumentJob $job, string $error): void
    {
        // Update progress to failed
        $progress = PipelineProgress::where('job_id', $job->id)->first();
        if ($progress) {
            $progress->update(['status' => 'failed']);
        }

        $job->fail($error);
        $job->document->markFailed($error);
    }
}
