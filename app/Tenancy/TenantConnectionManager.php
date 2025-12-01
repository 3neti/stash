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
     * Query the tenant database directly to check if migrations have run.
     */
    public function tenantSchemaInitialized(Tenant $tenant): bool
    {
        try {
            // Switch to tenant connection to check tables directly
            $dbName = $this->getTenantDatabaseName($tenant);
            
            // Ensure tenant connection is configured
            config([
                'database.connections.tenant' => [
                    'driver' => 'pgsql',
                    'host' => config('database.connections.pgsql.host'),
                    'port' => config('database.connections.pgsql.port'),
                    'database' => $dbName,
                    'username' => config('database.connections.pgsql.username'),
                    'password' => config('database.connections.pgsql.password'),
                    'charset' => config('database.connections.pgsql.charset'),
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'search_path' => 'public',
                    'sslmode' => 'prefer',
                ],
            ]);
            
            // Purge and reconnect to tenant DB
            DB::purge('tenant');
            
            // Check if campaigns table exists by querying tenant DB directly
            $result = DB::connection('tenant')->select(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_name = 'campaigns'"
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
        \Illuminate\Support\Facades\Log::debug('[TenantConnectionManager] Running tenant migrations', [
            'database' => $databaseName,
        ]);

        // Check if migrations table exists in the tenant database
        // We need to connect to tenant DB first to check
        try {
            // Try to connect to tenant and check for migrations table
            $result = DB::connection('tenant')->select(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = 'public' AND table_name = 'migrations'"
            );
            $migrationTableExists = ! empty($result);
            \Illuminate\Support\Facades\Log::debug('[TenantConnectionManager] Migrations table exists', [
                'exists' => $migrationTableExists,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[TenantConnectionManager] Could not check migrations table', [
                'error' => $e->getMessage(),
            ]);
            $migrationTableExists = false;
        }
        
        if ($migrationTableExists) {
            // Migrations table exists, do normal migrate
            \Illuminate\Support\Facades\Log::info('[TenantConnectionManager] Running migrate command');
            $exitCode = \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            \Illuminate\Support\Facades\Log::info('[TenantConnectionManager] Migrate completed', ['exit_code' => $exitCode]);
        } else {
            // Migrations table doesn't exist - this means migrations were never run on this DB
            // Use install+migrate instead of refresh to avoid dropping tables
            \Illuminate\Support\Facades\Log::info('[TenantConnectionManager] Running migrate:install and migrate commands');
            
            // First, ensure migrations table is created
            $installExitCode = \Illuminate\Support\Facades\Artisan::call('migrate:install', [
                '--database' => 'tenant',
            ]);
            \Illuminate\Support\Facades\Log::info('[TenantConnectionManager] Migrate:install completed', ['exit_code' => $installExitCode]);
            
            // Then run migrations
            $migrateExitCode = \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            \Illuminate\Support\Facades\Log::info('[TenantConnectionManager] Migrate completed', ['exit_code' => $migrateExitCode]);
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
