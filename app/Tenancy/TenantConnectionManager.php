<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Manages tenant database connections and lifecycle.
 */
class TenantConnectionManager
{
    /**
     * Switch the application to use a tenant's database connection.
     * Ensures database and schema exist (auto-create if needed).
     */
    public function switchToTenant(Tenant $tenant): void
    {
        $tenantDb = $this->getTenantDatabaseName($tenant);

        config([
            'database.connections.tenant' => [
                'driver' => 'pgsql',
                'host' => config('database.connections.pgsql.host'),
                'port' => config('database.connections.pgsql.port'),
                'database' => $tenantDb,
                'username' => config('database.connections.pgsql.username'),
                'password' => config('database.connections.pgsql.password'),
                'charset' => config('database.connections.pgsql.charset'),
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
        ]);

        // Purge any existing tenant connection to force reconnection
        DB::purge('tenant');

        // Create tenant database if it doesn't exist (e.g., in tests)
        if (! $this->tenantDatabaseExists($tenant)) {
            $this->createTenantDatabase($tenant);
            // Run tenant migrations
            $this->runTenantMigrations($tenantDb);
        } else {
            // Database exists - verify schema is initialized
            // This handles: migrate:fresh, restored backups, or any case where DB exists but tables don't
            if (! $this->tenantSchemaInitialized($tenant)) {
                $this->runTenantMigrations($tenantDb);
            }
        }

        // Set tenant as default connection
        DB::setDefaultConnection('tenant');
    }
    public function switchToCentral(): void
    {
        DB::setDefaultConnection('pgsql');
    }

    /**
     * Create a new database for the tenant.
     * Must be run outside of a transaction (PostgreSQL limitation).
     */
    public function createTenantDatabase(Tenant $tenant): void
    {
        $dbName = $this->getTenantDatabaseName($tenant);

        // PostgreSQL requires CREATE DATABASE to run outside a transaction
        $pdo = DB::connection('pgsql')->getPdo();
        
        // Check if we're in a transaction and commit it first
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        
        // Now safely execute CREATE DATABASE outside transaction
        $pdo->exec(sprintf('CREATE DATABASE "%s"', $dbName));
    }

    /**
     * Drop the tenant's database.
     *
     * CAUTION: This is destructive and cannot be undone.
     */
    public function dropTenantDatabase(Tenant $tenant): void
    {
        $dbName = $this->getTenantDatabaseName($tenant);

        // Terminate all connections to the database first
        DB::connection('pgsql')->statement(
            sprintf(
                "SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '%s'
                AND pid <> pg_backend_pid()",
                $dbName
            )
        );

        // Drop the database
        DB::connection('pgsql')->statement(
            sprintf('DROP DATABASE IF EXISTS "%s"', $dbName)
        );
    }

    /**
     * Check if a tenant database exists.
     */
    public function tenantDatabaseExists(Tenant $tenant): bool
    {
        $dbName = $this->getTenantDatabaseName($tenant);

        $result = DB::connection('pgsql')->select(
            'SELECT 1 FROM pg_database WHERE datname = ?',
            [$dbName]
        );

        return ! empty($result);
    }

    /**
     * Check if tenant schema is initialized (has required tables).
     * Query information_schema via the central connection for the specific tenant DB.
     */
    public function tenantSchemaInitialized(Tenant $tenant): bool
    {
        try {
            $dbName = $this->getTenantDatabaseName($tenant);

            // Check if 'campaigns' table exists inside the tenant database
            $result = DB::connection('pgsql')->select(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_catalog = ? AND table_schema = 'public' AND table_name = 'campaigns'",
                [$dbName]
            );

            return ! empty($result);
        } catch (\Exception $e) {
            // If we can't query, assume schema isn't initialized
            return false;
        }
    }

    /**
     * Run tenant migrations on the given database.
     * Automatically refreshes if migrations table doesn't exist in tenant database
     * (handles migration tracking being in central DB instead of tenant DB).
     */
    private function runTenantMigrations(string $databaseName): void
    {
        // Check if migrations table exists in the tenant database
        $queryResult = DB::connection('pgsql')->select(
            "SELECT 1 FROM information_schema.tables
             WHERE table_catalog = ? AND table_schema = 'public' AND table_name = 'migrations'",
            [$databaseName]
        );
        $migrationTableExists = ! empty($queryResult);
        
        if ($migrationTableExists) {
            // Migrations table exists, do normal migrate
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        } else {
            // Migrations table doesn't exist - this means migrations were never run on this DB
            // Use refresh to create migrations table and run all migrations
            \Illuminate\Support\Facades\Artisan::call('migrate:refresh', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
                '--seed' => false,
            ]);
        }
    }

    /**
     * Get the database name for a tenant.
     */
    public function getTenantDatabaseName(Tenant $tenant): string
    {
        return "tenant_{$tenant->id}";
    }
}
