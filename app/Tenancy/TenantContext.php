<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Events\TenantInitialized;
use App\Models\Tenant;

/**
 * Manages the current tenant context throughout the application lifecycle.
 *
 * Inspired by Spatie's TaskSwitcher pattern.
 */
class TenantContext
{
    private static ?Tenant $currentTenant = null;

    /**
     * Initialize tenancy for the given tenant.
     */
    public static function initialize(Tenant $tenant): void
    {
        self::$currentTenant = $tenant;

        // Switch database connection
        app(TenantConnectionManager::class)->switchToTenant($tenant);

        // Fire event for other services to bootstrap (cache, filesystem, etc.)
        event(new TenantInitialized($tenant));
    }

    /**
     * Get the current tenant.
     */
    public static function current(): ?Tenant
    {
        return self::$currentTenant;
    }

    /**
     * Check if a tenant is currently initialized.
     */
    public static function isInitialized(): bool
    {
        return self::$currentTenant !== null;
    }

    /**
     * Forget the current tenant and switch back to central database.
     */
    public static function forgetCurrent(): void
    {
        self::$currentTenant = null;
        app(TenantConnectionManager::class)->switchToCentral();
    }

    /**
     * Run a callback in the context of a specific tenant.
     *
     * Restores the previous tenant context after execution.
     */
    public static function run(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = self::$currentTenant;

        try {
            self::initialize($tenant);

            return $callback();
        } finally {
            if ($previousTenant) {
                self::initialize($previousTenant);
            } else {
                self::forgetCurrent();
            }
        }
    }
}
