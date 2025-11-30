<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;

/**
 * TestCase for tests that need tenant-scoped tables.
 *
 * Uses DatabaseTransactions on both pgsql and tenant connections.
 * Tenant migrations must be run manually: php artisan migrate --path=database/migrations/tenant
 */
abstract class TenantAwareTestCase extends TestCase
{
    use DatabaseTransactions;

    /**
     * The database connections that should have transactions.
     */
    protected array $connectionsToTransact = ['pgsql', 'tenant'];
}
