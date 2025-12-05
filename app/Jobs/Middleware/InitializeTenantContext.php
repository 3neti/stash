<?php

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;

class InitializeTenantContext
{
    public function __construct(
        protected ?string $tenantId = null
    ) {
    }

    /**
     * Handle the job middleware.
     */
    public function handle(mixed $job, callable $next): void
    {
        Log::info('[InitializeTenantContext] Middleware called', [
            'tenant_id' => $this->tenantId,
            'job' => get_class($job),
        ]);

        if (! $this->tenantId) {
            Log::warning('[InitializeTenantContext] No tenant ID provided');
            $next($job);
            return;
        }

        $tenant = Tenant::on('central')->find($this->tenantId);
        
        if (! $tenant) {
            Log::error('[InitializeTenantContext] Tenant not found', ['tenant_id' => $this->tenantId]);
            $next($job);
            return;
        }

        Log::info('[InitializeTenantContext] Initializing tenant context', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        TenantContext::run($tenant, fn () => $next($job));
    }
}
