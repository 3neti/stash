<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentSignedEvent;
use App\Models\DocumentJob;
use App\Models\KycTransaction;
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
            if (! $job->isRunning() && ! $job->isCompleted() && ! $job->isFailed()) {
                $job->start();
            }

            // Mark job as complete (idempotent - will no-op if already completed/failed)
            $job->complete();

            // Mark document as complete
            $job->document->markCompleted();

            Log::info('[Workflow] DocumentJob and Document marked as completed', [
                'job_id' => $job->id,
                'document_id' => $job->document->id,
            ]);

            // Broadcast signed document event if this is an e-signature workflow
            $this->broadcastSignedDocumentIfApplicable($job);
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

    /**
     * Broadcast DocumentSignedEvent if workflow completed e-signature processing.
     */
    protected function broadcastSignedDocumentIfApplicable(DocumentJob $job): void
    {
        try {
            // Check if this is an e-signature campaign
            $campaign = $job->campaign;
            if (!$campaign || $campaign->slug !== 'e-signature') {
                return;
            }

            // Find the electronic signature processor execution
            $signatureExecution = $job->processorExecutions()
                ->whereHas('processor', fn($q) => $q->where('slug', 'electronic-signature'))
                ->first();

            if (!$signatureExecution || !$signatureExecution->isCompleted()) {
                Log::warning('[Workflow] E-signature execution not found or incomplete', [
                    'job_id' => $job->id,
                ]);
                return;
            }

            // Extract signed document data
            $signedDoc = $signatureExecution->getFirstMedia('signed_documents');
            $signatureMark = $signatureExecution->getFirstMedia('signature_marks');

            if (!$signedDoc) {
                Log::warning('[Workflow] Signed document not found in media', [
                    'job_id' => $job->id,
                    'execution_id' => $signatureExecution->id,
                ]);
                return;
            }

            // Get transaction ID from KYC registry
            $kycTransaction = KycTransaction::where('document_job_id', $job->id)->first();
            if (!$kycTransaction) {
                Log::warning('[Workflow] KYC transaction not found for broadcast', [
                    'job_id' => $job->id,
                ]);
                return;
            }

            // Prepare signed document data
            $signedDocData = [
                'filename' => $signedDoc->file_name,
                'size_kb' => round($signedDoc->size / 1024, 2),
                'mime_type' => $signedDoc->mime_type,
                'download_url' => route('documents.signed.download', [
                    'execution' => $signatureExecution->id,
                    'tenant' => $kycTransaction->tenant_id,
                ]),
                'qr_watermarked' => $signedDoc->getCustomProperty('qr_watermarked', false),
                'signed_at' => $signedDoc->getCustomProperty('signed_at'),
                'verification_url' => $signedDoc->getCustomProperty('verification_url'),
            ];

            if ($signatureMark) {
                $signedDocData['signature_mark_url'] = $signatureMark->getUrl();
            }

            // Broadcast event
            broadcast(new DocumentSignedEvent(
                transactionId: $kycTransaction->transaction_id,
                documentJobId: $job->id,
                signedDocument: $signedDocData
            ));

            Log::info('[Workflow] DocumentSignedEvent broadcast', [
                'job_id' => $job->id,
                'transaction_id' => $kycTransaction->transaction_id,
                'filename' => $signedDocData['filename'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Workflow] Failed to broadcast signed document event', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - workflow completion should succeed even if broadcast fails
        }
    }
}
