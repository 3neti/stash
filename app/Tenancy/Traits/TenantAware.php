<?php

declare(strict_types=1);

namespace App\Tenancy\Traits;

use App\Models\Tenant;
use App\Tenancy\TenantContext;

/**
 * Trait for queue jobs that need to run in a tenant context.
 *
 * Automatically captures the current tenant when the job is dispatched
 * and restores it when the job executes.
 */
trait TenantAware
{
    public ?string $tenantId = null;

    /**
     * Capture the current tenant when the job is created.
     */
    public function __construct()
    {
        if ($tenant = TenantContext::current()) {
            $this->tenantId = $tenant->id;
        }
    }

    /**
     * Middleware to ensure the job runs in the tenant context.
     */
    public function middleware(): array
    {
        return [
            function ($job, $next) {
                if ($this->tenantId) {
                    $tenant = Tenant::on('pgsql')->find($this->tenantId);

                    if (! $tenant) {
                        throw new \RuntimeException("Tenant {$this->tenantId} not found");
                    }

                    return TenantContext::run($tenant, fn () => $next($job));
                }

                return $next($job);
            },
        ];
    }
}
