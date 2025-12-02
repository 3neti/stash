<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Workflows\DocumentProcessingWorkflow;
use Illuminate\Support\Facades\Log;
use Workflow\Events\WorkflowCompleted;

/**
 * Handle workflow completion for DocumentProcessingWorkflow.
 *
 * Updates DocumentJob and Document states when workflow finishes successfully.
 */
class WorkflowCompletedListener
{
    public function __construct(
        private TenancyService $tenancyService
    ) {}

    public function handle(WorkflowCompleted $event): void
    {
        // Fetch StoredWorkflow from database using configured model
        $storedWorkflowModel = config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class);
        $storedWorkflow = $storedWorkflowModel::find($event->workflowId);

        if (!$storedWorkflow) {
            Log::error('[Workflow] StoredWorkflow not found', ['workflow_id' => $event->workflowId]);
            return;
        }

        // Only handle DocumentProcessingWorkflow completions
        if ($storedWorkflow->class !== DocumentProcessingWorkflow::class) {
            return;
        }

        // Extract job ID and tenant ID from workflow arguments
        // Arguments are stored as serialized closures by Laravel Workflow
        try {
            $arguments = unserialize($storedWorkflow->arguments);
            $argumentsData = is_callable($arguments) ? $arguments() : $arguments;
            $jobId = $argumentsData[0] ?? null;
            $tenantId = $argumentsData[1] ?? null;
        } catch (\Throwable $e) {
            Log::error('[Workflow] Failed to unserialize arguments', [
                'workflow_id' => $storedWorkflow->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (!$jobId || !$tenantId) {
            Log::error('[Workflow] Missing job_id or tenant_id in completed workflow', [
                'workflow_id' => $storedWorkflow->id,
                'arguments' => $arguments,
            ]);
            return;
        }

        Log::info('[Workflow] DocumentProcessingWorkflow completed', [
            'workflow_id' => $storedWorkflow->id,
            'job_id' => $jobId,
            'tenant_id' => $tenantId,
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

            // Transition to running first if still pending (workflow just started)
            if (! $job->isRunning() && ! $job->isCompleted()) {
                $job->start();
            }

            // Mark job as complete
            $job->complete();

            // Mark document as complete
            $job->document->markCompleted();

            Log::info('[Workflow] DocumentJob and Document marked as completed', [
                'job_id' => $job->id,
                'document_id' => $job->document->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Workflow] Failed to handle workflow completion', [
                'workflow_id' => $event->workflowId,
                'job_id' => $jobId ?? null,
                'tenant_id' => $tenantId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
