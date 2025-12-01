<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Models\Campaign;
use App\Models\DocumentJob;
use App\Tenancy\TenantConnectionManager;
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
        try {
            Log::debug('[JobMiddleware] Starting tenant context setup', [
                'document_job_id' => $this->documentJobId,
                'tenant_id' => $job->tenantId ?? 'not_provided',
                'current_connection' => DB::getDefaultConnection(),
            ]);

            // If job has tenantId (provided at dispatch time), use it directly
            if ($job->tenantId) {
                Log::debug('[JobMiddleware] Using tenantId from job payload');
                $tenant = \App\Models\Tenant::on('pgsql')->findOrFail($job->tenantId);
                Log::debug('[JobMiddleware] Tenant loaded', ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]);
            } else {
                // Fall back to loading via DocumentJob → Campaign → Tenant chain
                Log::debug('[JobMiddleware] tenantId not in job, loading DocumentJob from central database');
                $documentJob = DocumentJob::on('pgsql')->findOrFail($this->documentJobId);
                Log::debug('[JobMiddleware] DocumentJob loaded', ['campaign_id' => $documentJob->campaign_id]);

                // Step 2: Load Campaign to get tenant_id
                Log::debug('[JobMiddleware] Loading Campaign from central database');
                $campaign = Campaign::on('pgsql')->findOrFail($documentJob->campaign_id);
                Log::debug('[JobMiddleware] Campaign loaded', ['tenant_id' => $campaign->tenant_id]);

                // Step 3: Load Tenant
                Log::debug('[JobMiddleware] Loading Tenant from central database');
                $tenant = \App\Models\Tenant::on('pgsql')->findOrFail($campaign->tenant_id);
                Log::debug('[JobMiddleware] Tenant loaded', ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]);
            }

            // Step 4: Initialize tenant context (switches to tenant connection)
            Log::debug('[JobMiddleware] Initializing tenant connection');
            $tenancyService = app(\App\Services\Tenancy\TenancyService::class);
            $tenancyService->initializeTenant($tenant);
            Log::debug('[JobMiddleware] Tenant connection initialized');

            // Verify tenant connection is active
            $tenantDb = DB::connection('tenant')->getDatabaseName();
            Log::debug('[JobMiddleware] Verified tenant database connection', ['database' => $tenantDb]);

            // Step 5: Execute the job with tenant context active
            Log::debug('[JobMiddleware] Executing job with tenant context');
            $next($job);
            Log::debug('[JobMiddleware] Job execution complete');

        } catch (\Throwable $e) {
            Log::error('[JobMiddleware] Failed to set tenant context for job', [
                'document_job_id' => $this->documentJobId,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500), // Limit trace length
            ]);

            throw $e;
        }
    }
}
