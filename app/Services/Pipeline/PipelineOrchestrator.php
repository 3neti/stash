<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Data\Pipeline\{PipelineConfigData, ProcessorConfigData};
use App\Data\Processors\{ProcessorContextData, ProcessorResultData};
use App\Exceptions\ProcessorException;
use App\Models\{Document, DocumentJob, Processor, ProcessorExecution};
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the execution of document processing pipelines.
 */
final readonly class PipelineOrchestrator
{
    public function __construct(
        private ProcessorRegistry $registry
    ) {}

    /**
     * Process a document through its pipeline (creates DocumentJob and executes).
     *
     * @param Document $document
     * @return DocumentJob The executed job
     * @throws ProcessorException
     */
    public function processDocument(Document $document): DocumentJob
    {
        // Create DocumentJob if one doesn't exist
        $documentJob = DocumentJob::create([
            'document_id' => $document->id,
            'campaign_id' => $document->campaign_id,
            'pipeline_instance' => $document->campaign->pipeline_config,
            'current_processor_index' => 0,
        ]);

        // Start the job
        $documentJob->start();
        $document->toProcessing();

        // Execute the pipeline
        $success = $this->executePipeline($documentJob);

        // Mark completion
        if ($success) {
            $documentJob->complete();
            $document->toCompleted();
        } else {
            $documentJob->fail('Pipeline execution failed');
            $document->toFailed();
        }

        return $documentJob;
    }

    /**
     * Execute a complete pipeline for a DocumentJob.
     *
     * @param DocumentJob $documentJob
     * @return bool True if all processors succeeded
     * @throws ProcessorException
     */
    public function executePipeline(DocumentJob $documentJob): bool
    {
        $document = $documentJob->document()->firstOrFail();
        $pipelineConfig = PipelineConfigData::from($documentJob->pipeline_instance);

        $previousOutputs = [];
        $allSuccessful = true;

        foreach ($pipelineConfig->processors as $index => $processorConfig) {
            $result = $this->executeProcessor(
                document: $document,
                documentJob: $documentJob,
                processorConfig: $processorConfig,
                processorIndex: $index,
                previousOutputs: $previousOutputs
            );

            // Store output for subsequent processors
            $previousOutputs[$processorConfig->id] = $result->output;

            // Update document metadata with processor output
            $this->updateDocumentMetadata($document, $processorConfig, $result);

            // Advance processor index
            $documentJob->current_processor_index = $index;
            $documentJob->save();

            if (!$result->success) {
                $allSuccessful = false;
                
                // Stop pipeline on failure (unless we implement branching/conditional logic later)
                break;
            }
        }

        return $allSuccessful;
    }

    /**
     * Execute a single processor in the pipeline.
     *
     * @param Document $document
     * @param DocumentJob $documentJob
     * @param ProcessorConfigData $processorConfig
     * @param int $processorIndex
     * @param array<string, mixed> $previousOutputs
     * @return ProcessorResultData
     * @throws ProcessorException
     */
    private function executeProcessor(
        Document $document,
        DocumentJob $documentJob,
        ProcessorConfigData $processorConfig,
        int $processorIndex,
        array $previousOutputs
    ): ProcessorResultData {
        // Get processor instance from registry
        $processor = $this->registry->get($processorConfig->id);

        // Check if processor can handle this document
        if (!$processor->canProcess($document)) {
            throw ProcessorException::documentNotSupported(
                $processorConfig->id,
                $document->id
            );
        }

        // Create context
        $context = new ProcessorContextData(
            documentJobId: $documentJob->id,
            processorIndex: $processorIndex,
            previousOutputs: $previousOutputs
        );

        // Create execution record
        $execution = $this->createExecution($documentJob, $processorConfig, $processorIndex);

        // Start execution
        $execution->state->transitionTo('running');

        // Execute processor
        $startTime = microtime(true);
        $result = $processor->handle($document, $processorConfig, $context);
        $duration = (int) ((microtime(true) - $startTime) * 1000); // Convert to milliseconds

        // Update execution record
        $this->updateExecution($execution, $result, $duration);

        return $result;
    }

    /**
     * Create a ProcessorExecution record.
     *
     * @param DocumentJob $documentJob
     * @param ProcessorConfigData $processorConfig
     * @param int $processorIndex
     * @return ProcessorExecution
     */
    private function createExecution(
        DocumentJob $documentJob,
        ProcessorConfigData $processorConfig,
        int $processorIndex
    ): ProcessorExecution {
        // Note: processor_id in the table references processors table
        // We need to lookup or create the processor record
        $slug = \Illuminate\Support\Str::slug($processorConfig->id);
        $processorRecord = Processor::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $processorConfig->id, // Use ID as name for now
                'class_name' => $processorConfig->type,
                'category' => 'custom',
                'is_active' => true,
            ]
        );

        return ProcessorExecution::create([
            'job_id' => $documentJob->id,
            'processor_id' => $processorRecord->id,
            'input_data' => [], // Empty for now, can populate with document metadata
            'config' => $processorConfig->config,
        ]);
    }

    /**
     * Update a ProcessorExecution record with results.
     *
     * @param ProcessorExecution $execution
     * @param ProcessorResultData $result
     * @param int $duration
     * @return void
     */
    private function updateExecution(
        ProcessorExecution $execution,
        ProcessorResultData $result,
        int $duration
    ): void {
        $execution->update([
            'output_data' => $result->output,
            'error_message' => $result->error,
            'duration_ms' => $duration,
            'tokens_used' => $result->tokensUsed ?? 0,
            'cost_credits' => $result->costCredits ?? 0,
            'completed_at' => now(),
        ]);

        // Transition state based on result
        if ($result->success) {
            $execution->state->transitionTo('completed');
        } else {
            $execution->state->transitionTo('failed');
        }
    }

    /**
     * Update document metadata with processor output.
     *
     * @param Document $document
     * @param ProcessorConfigData $processorConfig
     * @param ProcessorResultData $result
     * @return void
     */
    private function updateDocumentMetadata(
        Document $document,
        ProcessorConfigData $processorConfig,
        ProcessorResultData $result
    ): void {
        if (!$result->success) {
            return;
        }

        $metadata = $document->metadata ?? [];

        // Store processor-specific outputs in metadata
        match ($processorConfig->type) {
            'ocr' => $metadata['extracted_text'] = $result->output['text'] ?? null,
            'classification' => $metadata['category'] = $result->output['category'] ?? null,
            'extraction' => $metadata['extracted_fields'] = $result->output['fields'] ?? null,
            default => null,
        };

        $document->metadata = $metadata;
        $document->save();
    }
}
