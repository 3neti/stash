<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to initialize tenancy for HTTP requests.
 * 
 * Identifies the tenant from the request domain and initializes the tenant context.
 */
class InitializeTenancy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->identifyTenant($request);

        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        if (!$tenant->isActive()) {
            abort(403, 'Tenant is not active');
        }

        TenantContext::initialize($tenant);

        return $next($request);
    }

    /**
     * Identify the tenant from the request.
     */
    private function identifyTenant(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Try to find tenant by domain
        return Tenant::on('pgsql')
            ->whereHas('domains', fn ($query) => $query->where('domain', $host))
            ->first();
    }
}
