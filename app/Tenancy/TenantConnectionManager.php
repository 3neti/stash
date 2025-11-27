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

        // Set tenant as default connection
        DB::setDefaultConnection('tenant');
    }

    /**
     * Switch back to the central database connection.
     */
    public function switchToCentral(): void
    {
        DB::setDefaultConnection('pgsql');
    }

    /**
     * Create a new database for the tenant.
     */
    public function createTenantDatabase(Tenant $tenant): void
    {
        $dbName = $this->getTenantDatabaseName($tenant);

        // Use central connection to create database
        DB::connection('pgsql')->statement(
            sprintf('CREATE DATABASE "%s"', $dbName)
        );
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
            "SELECT 1 FROM pg_database WHERE datname = ?",
            [$dbName]
        );

        return !empty($result);
    }

    /**
     * Get the database name for a tenant.
     */
    public function getTenantDatabaseName(Tenant $tenant): string
    {
        return "tenant_{$tenant->id}";
    }
}
