<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Workflows\Activities\GenericProcessorActivity;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * DocumentProcessingWorkflow
 *
 * A durable workflow that orchestrates document processing through multiple activities.
 * Based on Laravel Workflow (inspired by Temporal) - uses generator-based async/await pattern.
 *
 * This workflow dynamically executes processors from the campaign's pipeline_config,
 * allowing any number and type of processors to be executed without code changes.
 */
class DocumentProcessingWorkflow extends Workflow
{
    /**
     * Signal data storage for KYC callbacks
     * Maps transaction_id => callback_data
     */
    private array $callbackSignals = [];
    /**
     * Signal method to receive KYC callback data.
     * Called externally by FetchKycDataFromCallback job.
     */
    #[SignalMethod]
    public function receiveKycCallback(string $transactionId, array $callbackData): void
    {
        $this->callbackSignals[$transactionId] = $callbackData;
        
        \Illuminate\Support\Facades\Log::info('[Workflow] receiveKycCallback method called', [
            'transaction_id' => $transactionId,
            'callback_data' => $callbackData,
            'existing_signals' => array_keys($this->callbackSignals),
        ]);
    }

    /**
     * Execute the document processing workflow.
     *
     * Dynamically executes all processors configured in the campaign's pipeline.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param string $tenantId The Tenant ULID for context
     * @return \Generator
     */
    public function execute(string $documentJobId, string $tenantId)
    {
        // Laravel Workflow uses generator-based async/await (like Temporal)
        // Each `yield` creates a checkpoint - if workflow crashes, it resumes from last checkpoint

        // Get processor configurations from the DocumentJob
        $processorConfigs = $this->getProcessorConfigs($documentJobId, $tenantId);
        $results = [];

        // Execute each processor dynamically
        foreach ($processorConfigs as $index => $processorConfig) {
            $result = yield ActivityStub::make(
                GenericProcessorActivity::class,
                $documentJobId,
                $index,          // Processor index in pipeline
                $results,        // Previous results for context
                $tenantId
            );

            // Check if processor is awaiting external callback (e.g., KYC verification)
            if (($result['awaiting_callback'] ?? false) && !empty($result['transaction_id'])) {
                $transactionId = $result['transaction_id'];
                
                \Illuminate\Support\Facades\Log::info('[Workflow] Waiting for external callback signal', [
                    'transaction_id' => $transactionId,
                    'document_job_id' => $documentJobId,
                    'result' => $result,
                ]);
                
                // Wait for signal with 24-hour timeout
                // Returns true if signal received, false if timeout
                $signalReceived = yield WorkflowStub::awaitWithTimeout(
                    86400, // 24 hours in seconds
                    fn() => isset($this->callbackSignals[$transactionId])
                );
                
                if (!$signalReceived) {
                    // Timeout occurred - callback never arrived
                    \Illuminate\Support\Facades\Log::warning('[Workflow] Callback timeout - no signal received', [
                        'transaction_id' => $transactionId,
                        'timeout_hours' => 24,
                    ]);
                    
                    // Merge timeout status into result
                    $results[] = array_merge($result, [
                        'kyc_status' => 'timeout',
                        'callback_received' => false,
                        'error' => 'KYC callback timeout after 24 hours',
                    ]);
                    
                    // Fail workflow on timeout
                    throw new \Exception("KYC callback timeout for transaction {$transactionId}");
                } else {
                    // Signal received - get callback data
                    $callbackData = $this->callbackSignals[$transactionId];
                    
                    \Illuminate\Support\Facades\Log::info('[Workflow] Callback signal processed', [
                        'transaction_id' => $transactionId,
                        'callback_data' => $callbackData,
                    ]);
                    
                    $results[] = array_merge($result, $callbackData, [
                        'callback_received' => true,
                    ]);
                }
            } else {
                // No callback needed - store result directly
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Get processor configurations from DocumentJob.
     *
     * This helper method initializes tenant context and reads the pipeline config.
     * Note: This is called once at workflow start, not re-executed on resume.
     *
     * @param string $documentJobId
     * @param string $tenantId
     * @return array
     */
    private function getProcessorConfigs(string $documentJobId, string $tenantId): array
    {
        // Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // Load DocumentJob and return processor configs
        $job = DocumentJob::findOrFail($documentJobId);
        return $job->pipeline_instance['processors'] ?? [];
    }
}
