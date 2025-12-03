<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Workflows\Activities\GenericProcessorActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

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

            // Store result for next processor
            $results[] = $result;
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
