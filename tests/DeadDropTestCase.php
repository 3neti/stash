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
 * Tenant migrations are automatically loaded via TenancyServiceProvider,
 * so RefreshDatabase handles both central and tenant tables.
 */
abstract class DeadDropTestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Define database connections to be refreshed.
     */
    protected function connectionsToTransact(): array
    {
        return ['pgsql', 'tenant'];
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
