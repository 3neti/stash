<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantFromUser
{
    /**
     * Handle an incoming request.
     *
     * Initialize tenant context from authenticated user's tenant_id.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::on('pgsql')->find($user->tenant_id);

            if ($tenant) {
                TenantContext::initialize($tenant);
            }
        }

        $response = $next($request);

        // Clean up tenant context after request
        if ($user && $user->tenant_id) {
            TenantContext::forgetCurrent();
        }

        return $response;
    }
}
