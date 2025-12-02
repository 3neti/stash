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
     *
     * @param string $documentJobId The DocumentJob ID (ULID)
     * @param string|null $tenantId Optional: Tenant ID for direct lookup (avoids central DB query)
     */
    public function __construct(
        private readonly string $documentJobId,
        private readonly ?string $tenantId = null
    ) {}

    /**
     * Process the queued job.
     */
    public function handle(object $job, Closure $next): void
    {
        try {
            Log::debug('[JobMiddleware] Starting tenant context setup', [
                'document_job_id' => $this->documentJobId,
                'tenant_id_provided' => $this->tenantId ?? 'not_provided',
                'current_connection' => DB::getDefaultConnection(),
            ]);

            // If tenantId was provided at dispatch time, use it directly (fast path)
            if ($this->tenantId) {
                Log::debug('[JobMiddleware] Using tenantId from middleware constructor');
                $tenant = \App\Models\Tenant::on('central')->findOrFail($this->tenantId);
                Log::debug('[JobMiddleware] Tenant loaded', ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]);
            } else {
                // Fall back to loading DocumentJob from tenant DB to get tenant_id
                Log::debug('[JobMiddleware] tenantId not provided, loading DocumentJob to get tenant_id');
                $documentJob = DocumentJob::findOrFail($this->documentJobId);
                Log::debug('[JobMiddleware] DocumentJob loaded', ['tenant_id' => $documentJob->tenant_id]);

                // Load Tenant from central DB
                Log::debug('[JobMiddleware] Loading Tenant from central database');
                $tenant = \App\Models\Tenant::on('central')->findOrFail($documentJob->tenant_id);
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
