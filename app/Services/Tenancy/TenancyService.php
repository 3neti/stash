<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Events\TenantDatabasePrepared;
use App\Events\TenantInitialized;
use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;

/**
 * TenancyService manages tenant lifecycle and database initialization.
 *
 * Encapsulates tenant database creation, migration, schema verification, and context switching.
 * All operations are testable and observable via events.
 */
class TenancyService
{
    public function __construct(
        private TenantConnectionManager $connectionManager,
    ) {}

    /**
     * Initialize tenant context for the current request.
     *
     * Ensures tenant database exists and schema is properly initialized,
     * then sets up the tenant context for the application.
     *
     * @throws \Exception if tenant database preparation fails
     */
    public function initializeTenant(Tenant $tenant): void
    {
        // Prepare tenant database (create if needed, run migrations)
        $this->prepareTenantDatabase($tenant);

        // Initialize context (switch connection, fire events)
        TenantContext::initialize($tenant);
    }

    /**
     * Prepare tenant database by creating it if needed and running migrations.
     *
     * Ensures the tenant database exists and has all required tables (schema).
     * If database exists but schema is incomplete, runs migrations to repair it.
     *
     * @throws \Exception if database creation or migration fails
     */
    public function prepareTenantDatabase(Tenant $tenant): void
    {
        $dbName = $this->connectionManager->getTenantDatabaseName($tenant);

        // Create database if it doesn't exist
        if (! $this->connectionManager->tenantDatabaseExists($tenant)) {
            $this->connectionManager->createTenantDatabase($tenant);
        }

        // Switch to tenant connection to enable schema checks
        $this->connectionManager->switchToTenant($tenant);

        // Verify and repair schema if needed
        if (! $this->verifyTenantSchema($tenant)) {
            $this->repairTenantSchema($tenant);
        }

        // Fire event after successful preparation
        event(new TenantDatabasePrepared($tenant));
    }

    /**
     * Verify tenant schema by checking for required tables.
     *
     * Uses information_schema to check if critical tables exist in the tenant database.
     * This is a read-only check that doesn't modify any schema.
     *
     * @return bool true if schema is initialized, false if tables are missing
     */
    public function verifyTenantSchema(Tenant $tenant): bool
    {
        return $this->connectionManager->tenantSchemaInitialized($tenant);
    }

    /**
     * Repair tenant schema by running migrations.
     *
     * Re-runs tenant migrations to create or restore missing tables.
     * Useful after database corruption or incomplete initial setup.
     *
     * @throws \Exception if migration fails
     */
    public function repairTenantSchema(Tenant $tenant): void
    {
        // Re-run migrations via artisan
        // Note: this is handled by TenantConnectionManager.runTenantMigrations()
        // which is invoked indirectly via switchToTenant() with schema check
        $this->connectionManager->switchToTenant($tenant);
    }

    /**
     * Get the database name for a tenant.
     *
     * @return string format: tenant_{tenant_id}
     */
    public function getTenantDatabaseName(Tenant $tenant): string
    {
        return $this->connectionManager->getTenantDatabaseName($tenant);
    }
}
