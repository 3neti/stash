<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

/**
 * Base test case for all tests.
 * 
 * Provides helper methods for common testing patterns:
 * - createTenant(): Create test tenants
 * - createUserWithTenant(): Create users associated with tenants
 * - inTenantContext(): Execute code within tenant context
 * - assertNoDatabaseErrors(): Assert no database errors in responses
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Set up the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable auto-onboarding globally for all tests to prevent observer interference
        Config::set('app.tenant_auto_onboarding', false);
    }
    
    /**
     * Create a tenant with default attributes.
     * 
     * @param array $attributes Optional attributes to override defaults
     * @return Tenant
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::on('central')->create(array_merge([
            'name' => 'Test Organization',
            'slug' => 'test-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Create a user associated with a tenant.
     * 
     * @param Tenant $tenant The tenant to associate the user with
     * @param array $attributes Optional attributes for the user
     * @return User
     */
    protected function createUserWithTenant(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        return $user;
    }

    /**
     * Run a callback within tenant context.
     * 
     * @param Tenant $tenant The tenant context to run in
     * @param callable $callback The callback to execute
     * @return mixed
     */
    protected function inTenantContext(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::run($tenant, $callback);
    }

    /**
     * Assert response has no database errors.
     * 
     * @param TestResponse $response The response to check
     * @return void
     */
    protected function assertNoDatabaseErrors(TestResponse $response): void
    {
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('Undefined table');
        $response->assertDontSee('does not exist');
    }
}
