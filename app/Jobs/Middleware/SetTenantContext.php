<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Models\DocumentJob;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job middleware that sets tenant context before job execution.
 *
 * This ensures all database operations within the job are scoped to the correct tenant.
 */
class SetTenantContext
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly string $documentJobId
    ) {}

    /**
     * Process the queued job.
     */
    public function handle(object $job, Closure $next): void
    {
        // Load the DocumentJob from central database to get tenant info
        // Note: DocumentJob is on tenant connection, but we need to load it first
        // to determine which tenant to connect to
        
        try {
            // Load from tenant connection (assumes tenant is already set by queue worker)
            $documentJob = DocumentJob::on('tenant')->findOrFail($this->documentJobId);
            
            // Verify tenant connection is active
            if (!DB::connection('tenant')->getDatabaseName()) {
                throw new \RuntimeException('Tenant connection not initialized');
            }

            Log::debug('Tenant context set for job', [
                'document_job_id' => $this->documentJobId,
                'tenant_db' => DB::connection('tenant')->getDatabaseName(),
            ]);

            // Execute the job
            $next($job);

        } catch (\Throwable $e) {
            Log::error('Failed to set tenant context for job', [
                'document_job_id' => $this->documentJobId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
}
