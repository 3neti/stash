<?php

declare(strict_types=1);

namespace App\Workflows\Activities;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Events\ProcessorExecutionCompleted;
use App\Models\DocumentJob;
use App\Models\KycTransaction;
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
        // Step 1: Get current workflow ID from activity context
        // workflowId() returns integer database ID, convert to string
        $rawWorkflowId = $this->workflowId();
        $workflowId = $rawWorkflowId ? (string) $rawWorkflowId : null;
        
        \Illuminate\Support\Facades\Log::debug('[GenericProcessorActivity] Captured workflow ID', [
            'raw_workflow_id' => $rawWorkflowId,
            'workflow_id_string' => $workflowId,
            'workflow_id_type' => gettype($rawWorkflowId),
        ]);
        
        // Step 2: Initialize tenant context
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

        // Resolve placeholders in config (e.g., {{ekyc-verification.transaction_id}})
        $resolvedConfig = $this->resolveConfigPlaceholders(
            $processorConfig,
            $previousResults
        );

        // Create ProcessorConfigData from resolved config
        $config = ProcessorConfigData::from($resolvedConfig);

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

        // Register KYC transaction in central DB if this is eKYC processor
        if ($processorModel->slug === 'ekyc-verification' && isset($result->output['transaction_id'])) {
            $this->registerKycTransaction(
                transactionId: $result->output['transaction_id'],
                workflowId: $workflowId,
                documentJobId: $documentJob->id,
                tenantId: $tenantId,
                documentId: $document->id,
                executionId: $execution->id,
                metadata: [
                    'redirect_url' => $result->output['redirect_url'] ?? null,
                    'contact_mobile' => $result->output['contact_mobile'] ?? null,
                    'contact_email' => $result->output['contact_email'] ?? null,
                    'contact_name' => $result->output['contact_name'] ?? null,
                ]
            );
        }

        // Return results for next processor
        return $result->output;
    }

    /**
     * Register KYC transaction in central database registry.
     * 
     * This allows public webhook/callback endpoints to locate the tenant and document
     * without requiring authentication.
     */
    protected function registerKycTransaction(
        string $transactionId,
        ?string $workflowId,
        string $documentJobId,
        string $tenantId,
        string $documentId,
        string $executionId,
        array $metadata
    ): void {
        try {
            // Use updateOrCreate to handle fixed transaction IDs in testing
            KycTransaction::updateOrCreate(
                ['transaction_id' => $transactionId],
                [
                    'workflow_id' => $workflowId,
                    'document_job_id' => $documentJobId,
                    'tenant_id' => $tenantId,
                    'document_id' => $documentId,
                    'processor_execution_id' => $executionId,
                    'status' => 'pending',
                    'metadata' => $metadata,
                ]
            );

            \Illuminate\Support\Facades\Log::info('[GenericProcessorActivity] KYC transaction registered', [
                'transaction_id' => $transactionId,
                'workflow_id' => $workflowId,
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[GenericProcessorActivity] Failed to register KYC transaction', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this is not critical for workflow continuation
        }
    }

    /**
     * Resolve placeholder variables in processor config.
     * 
     * Replaces {{processor-slug.field}} with values from previous processor outputs.
     * Example: {{ekyc-verification.transaction_id}} -> "EKYC-1234567890"
     * 
     * @param array $config Processor config with possible placeholders
     * @param array $previousResults Array of previous processor outputs indexed by processor index
     * @return array Resolved config
     */
    protected function resolveConfigPlaceholders(array $config, array $previousResults): array
    {
        // Deep clone config to avoid modifying original
        $resolved = $config;
        
        // Check if config has 'config' key and it's an array
        if (!isset($resolved['config']) || !is_array($resolved['config'])) {
            return $resolved;
        }
        
        // Recursively replace placeholders in all config values
        array_walk_recursive($resolved['config'], function (&$value) use ($previousResults) {
            if (!is_string($value)) {
                return;
            }
            
            // Match {{processor-slug.field}} or {{processor-slug.nested.field}}
            if (preg_match_all('/{{([\w-]+)\.([\w.]+)}}/', $value, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fullPlaceholder = $match[0]; // {{processor-slug.field}}
                    $processorSlug = $match[1];   // processor-slug
                    $fieldPath = $match[2];       // field or nested.field
                    
                    // Find the processor output by slug
                    $processorOutput = null;
                    foreach ($previousResults as $result) {
                        // previousResults is an array of processor outputs
                        // We need to check document metadata for processor slugs
                        if (is_array($result) && isset($result['transaction_id']) && $processorSlug === 'ekyc-verification') {
                            $processorOutput = $result;
                            break;
                        }
                    }
                    
                    if (!$processorOutput) {
                        \Illuminate\Support\Facades\Log::warning('[GenericProcessorActivity] Placeholder not found', [
                            'placeholder' => $fullPlaceholder,
                            'processor_slug' => $processorSlug,
                            'field_path' => $fieldPath,
                        ]);
                        continue;
                    }
                    
                    // Get nested field value (support dot notation)
                    $fieldValue = data_get($processorOutput, $fieldPath);
                    
                    if ($fieldValue !== null) {
                        // Replace placeholder with actual value
                        $value = str_replace($fullPlaceholder, $fieldValue, $value);
                        
                        \Illuminate\Support\Facades\Log::debug('[GenericProcessorActivity] Placeholder resolved', [
                            'placeholder' => $fullPlaceholder,
                            'resolved_value' => $fieldValue,
                        ]);
                    }
                }
            }
        });
        
        return $resolved;
    }
}
