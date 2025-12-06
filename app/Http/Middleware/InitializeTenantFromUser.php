<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantFromUser
{
    /**
     * Handle an incoming request.
     *
     * Initialize tenant context from authenticated user's tenant_id.
     * Ensures tenant database and schema exist before routing to controller.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatable = $request->user();
        $tenantId = null;
        $authenticatableType = $authenticatable ? get_class($authenticatable) : null;
        
        Log::debug('[Middleware] InitializeTenantFromUser', [
            'authenticatable_id' => $authenticatable?->id,
            'authenticatable_type' => $authenticatableType,
        ]);

        // Get tenant_id from the authenticated model (User or Campaign)
        if ($authenticatable && isset($authenticatable->tenant_id)) {
            $tenantId = $authenticatable->tenant_id;
            Log::debug('[Middleware] Found tenant_id on authenticatable', ['tenant_id' => $tenantId]);
        }

        if ($tenantId) {
            Log::debug('[Middleware] Looking up tenant', ['tenant_id' => $tenantId]);
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                Log::debug('[Middleware] Found tenant', ['tenant_id' => $tenant->id, 'tenant_name' => $tenant->name]);
                // Use TenancyService to initialize tenant
                // This ensures database and schema exist before any queries
                $tenancyService = app(TenancyService::class);
                Log::debug('[Middleware] Initializing tenant context');
                $tenancyService->initializeTenant($tenant);
                Log::debug('[Middleware] Tenant context initialized');
            } else {
                Log::warning('[Middleware] Tenant not found', ['tenant_id' => $tenantId]);
            }
        } else {
            Log::debug('[Middleware] No tenant_id found on authenticatable');
        }

        $response = $next($request);

        // Do NOT clean up tenant context here - events and responses may still need it.
        // The framework will clean up after the response is sent to the client.
        // Keeping tenant context active ensures any event listeners (e.g., webhooks)
        // can still access tenant-scoped data without "Undefined table" errors.

        return $response;
    }
}
