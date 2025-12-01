<?php

declare(strict_types=1);

namespace App\Jobs\Pipeline;

use App\Jobs\Middleware\SetTenantContext;
use App\Models\DocumentJob;
use App\Services\Pipeline\DocumentProcessingPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a DocumentJob through its configured pipeline.
 *
 * This job is tenant-aware and retriable.
 */
class ProcessDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The unique ID for this job instance.
     */
    public string $uniqueId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $documentJobId
    ) {
        $this->uniqueId = $documentJobId;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new SetTenantContext($this->documentJobId),
        ];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentProcessingPipeline $pipeline): void
    {
        Log::info('ProcessDocumentJob started', [
            'document_job_id' => $this->documentJobId,
            'attempt' => $this->attempts(),
        ]);

        // Load the DocumentJob
        $documentJob = DocumentJob::findOrFail($this->documentJobId);

        // Transition through states: pending → queued → running
        if ($documentJob->state->canTransitionTo('queued')) {
            $documentJob->state->transitionTo('queued');
        }
        if ($documentJob->state->canTransitionTo('running')) {
            $documentJob->state->transitionTo('running');
            $documentJob->update(['started_at' => now()]);
        }

        try {
            // Execute the next stage of the pipeline
            $continueProcessing = $pipeline->executeNextStage($documentJob);

            if ($continueProcessing) {
                // More stages to process - re-dispatch job
                ProcessDocumentJob::dispatch($documentJob);

                Log::info('ProcessDocumentJob advancing to next stage', [
                    'document_job_id' => $this->documentJobId,
                    'stage' => $documentJob->current_processor_index,
                ]);
            } else {
                // All stages complete or failed
                Log::info('ProcessDocumentJob pipeline completed', [
                    'document_job_id' => $this->documentJobId,
                    'success' => $documentJob->isCompleted(),
                ]);
            }
        } catch (\Throwable $e) {
            $shouldRetry = $this->handleFailure($documentJob, $e->getMessage(), $e);

            // Only re-throw if we should retry (let Laravel handle the retry)
            if ($shouldRetry) {
                throw $e;
            }
        }
    }

    /**
     * Handle job failure.
     *
     * @return bool True if job should retry, false if exhausted
     */
    private function handleFailure(DocumentJob $documentJob, string $error, ?\Throwable $exception = null): bool
    {
        Log::error('ProcessDocumentJob failed', [
            'document_job_id' => $this->documentJobId,
            'attempt' => $this->attempts(),
            'error' => $error,
            'exception' => $exception?->getMessage(),
        ]);

        // Increment attempts
        $documentJob->incrementAttempts();

        // Check if we can retry
        if (! $documentJob->canRetry()) {
            // Final failure - mark as failed
            $documentJob->fail($error);

            // Update document state
            $document = $documentJob->document;
            $document->markFailed($error);

            Log::error('ProcessDocumentJob exhausted retries', [
                'document_job_id' => $this->documentJobId,
                'attempts' => $documentJob->attempts,
            ]);

            return false; // Don't retry
        }

        return true; // Should retry
    }

    /**
     * Handle a job failure (called by Laravel when job fails).
     *
     * This is only called if the exception was re-thrown (for retries).
     * If we already handled the failure in handleFailure(), this does nothing.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDocumentJob moved to failed queue', [
            'document_job_id' => $this->documentJobId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Only handle if not already failed
        // (handleFailure() already marked it as failed if retries exhausted)
        try {
            $documentJob = DocumentJob::find($this->documentJobId);
            if ($documentJob && ! $documentJob->isFailed()) {
                // This should rarely happen, but just in case
                $documentJob->incrementAttempts();
                $documentJob->fail($exception->getMessage());
                $documentJob->document->markFailed($exception->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('Failed to mark job as failed', [
                'document_job_id' => $this->documentJobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * Exponential backoff: 1 min, 5 min, 15 min
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1m, 5m, 15m
    }
}
