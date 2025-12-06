<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Sets up tenant database for multi-tenant tests.
 * 
 * This trait:
 * - Uses RefreshDatabase to ensure clean state
 * - Configures both 'central' and 'tenant' connections to use transactions
 * - Runs tenant migrations after central database is refreshed
 * - Auto-creates a default tenant and initializes tenant context
 * 
 * Usage:
 *   class MyTest extends TestCase
 *   {
 *       use SetUpsTenantDatabase;
 *       
 *       test('example', function () {
 *           // Default tenant already initialized, can use tenant models directly
 *           $campaign = Campaign::factory()->create();
 *           
 *           // Or create additional tenants
 *           $otherTenant = $this->createTenant(['slug' => 'other']);
 *           $this->inTenantContext($otherTenant, function () {
 *               // Test code using other tenant database
 *           });
 *       });
 *   }
 */
trait SetUpsTenantDatabase
{
    use RefreshDatabase;

    /**
     * Default tenant for tests.
     */
    protected ?Tenant $defaultTenant = null;

    /**
     * Define database connections to refresh and transact.
     * 
     * Both central and tenant connections need transactional support
     * to ensure proper test isolation.
     * 
     * @var array<string>
     */
    protected array $connectionsToTransact = ['central', 'tenant'];

    /**
     * Run tenant migrations after refreshing central database.
     * 
     * This hook is called by RefreshDatabase trait after migrations
     * are run on the default (central) connection.
     * 
     * @return void
     */
    protected function afterRefreshingDatabase(): void
    {
        $this->artisan('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        // Auto-create default tenant and initialize context
        $this->defaultTenant = $this->createTenant([
            'slug' => 'test-tenant',
            'name' => 'Test Tenant',
        ]);

        // Initialize tenant context globally for this test
        app(TenancyService::class)->initializeTenant($this->defaultTenant);
    }
}
