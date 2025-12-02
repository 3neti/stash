<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for DeadDrop package tests.
 *
 * Provides common setup, helpers, and patterns for testing
 * DeadDrop mono-repo packages.
 *
 * This test case supports both central and tenant database contexts:
 * - Central database: Hosts cross-tenant data (Tenants, Users, Domains)
 * - Tenant database: Hosts tenant-specific data (Campaigns, Documents, etc.)
 *
 * Central and tenant migrations are automatically refreshed for each test,
 * and tenant connections are properly initialized.
 */
abstract class DeadDropTestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Define database connections to be refreshed during tests.
     * Both central and tenant connections need to be transacted.
     */
    protected function connectionsToTransact(): array
    {
        return ['central', 'tenant'];
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional DeadDrop-specific setup can go here
        // e.g., seeding common test data, configuring test credentials, etc.
    }

    /**
     * Run migrations on tenant connection after refreshing database.
     */
    protected function afterRefreshingDatabase(): void
    {
        // Run tenant migrations on tenant connection
        $this->artisan('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    protected function mockServices(array $mocks): void
    {
        foreach ($mocks as $abstract => $mock) {
            $this->app->instance($abstract, $mock);
        }
    }
}
