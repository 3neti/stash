<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Sets up tenant database for multi-tenant tests.
 * 
 * This trait:
 * - Uses RefreshDatabase to ensure clean state
 * - Configures both 'central' and 'tenant' connections to use transactions
 * - Runs tenant migrations after central database is refreshed
 * 
 * Usage:
 *   class MyTest extends TestCase
 *   {
 *       use SetUpsTenantDatabase;
 *       
 *       test('example', function () {
 *           $tenant = $this->createTenant();
 *           $this->inTenantContext($tenant, function () {
 *               // Test code using tenant database
 *           });
 *       });
 *   }
 */
trait SetUpsTenantDatabase
{
    use RefreshDatabase;

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
    }
}
