<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Workflows\DocumentProcessingWorkflow;
use Illuminate\Support\Facades\Log;
use Workflow\Events\WorkflowFailed;

/**
 * Handle workflow failures for DocumentProcessingWorkflow.
 *
 * Updates DocumentJob and Document states when workflow fails.
 */
class WorkflowFailedListener
{
    public function __construct(
        private TenancyService $tenancyService
    ) {}

    public function handle(WorkflowFailed $event): void
    {
        // Fetch StoredWorkflow from database using configured model
        $storedWorkflowModel = config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class);
        $storedWorkflow = $storedWorkflowModel::find($event->workflowId);

        if (!$storedWorkflow) {
            Log::error('[Workflow] StoredWorkflow not found', ['workflow_id' => $event->workflowId]);
            return;
        }

        // Only handle DocumentProcessingWorkflow failures
        if ($storedWorkflow->class !== DocumentProcessingWorkflow::class) {
            return;
        }

        // Extract job ID and tenant ID from workflow arguments
        $arguments = json_decode($storedWorkflow->arguments, true);
        $jobId = $arguments[0] ?? null;
        $tenantId = $arguments[1] ?? null;

        if (!$jobId || !$tenantId) {
            Log::error('[Workflow] Missing job_id or tenant_id in failed workflow', [
                'workflow_id' => $storedWorkflow->id,
                'arguments' => $arguments,
            ]);
            return;
        }

        // Get error details from output (workflow output contains error info for failed workflows)
        $errorMessage = $event->output ?: 'Unknown workflow error';

        Log::error('[Workflow] DocumentProcessingWorkflow failed', [
            'workflow_id' => $storedWorkflow->id,
            'job_id' => $jobId,
            'tenant_id' => $tenantId,
            'error' => $errorMessage,
            'timestamp' => $event->timestamp,
        ]);

        try {
            // Initialize tenant context
            $tenant = Tenant::on('central')->findOrFail($tenantId);
            $this->tenancyService->initializeTenant($tenant);

            // Load DocumentJob from tenant database
            $job = DocumentJob::find($jobId);

            if (!$job) {
                Log::error('[Workflow] DocumentJob not found', [
                    'job_id' => $jobId,
                    'tenant_id' => $tenantId,
                ]);
                return;
            }

            // Mark job as failed
            $job->fail($errorMessage);

            // Mark document as failed
            $job->document->markFailed($errorMessage);

            Log::info('[Workflow] DocumentJob and Document marked as failed', [
                'job_id' => $job->id,
                'document_id' => $job->document->id,
                'error' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Workflow] Failed to handle workflow failure', [
                'workflow_id' => $event->workflowId,
                'job_id' => $jobId ?? null,
                'tenant_id' => $tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
