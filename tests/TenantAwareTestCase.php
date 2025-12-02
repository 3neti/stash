<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;

/**
 * TestCase for tests that need tenant-scoped tables.
 *
 * Uses DatabaseTransactions on both central and tenant connections.
 * Supports testing multi-tenant scenarios with proper database isolation.
 */
abstract class TenantAwareTestCase extends TestCase
{
    use DatabaseTransactions;

    /**
     * The database connections that should have transactions.
     * Both central and tenant connections need transactional support for isolation.
     */
    protected array $connectionsToTransact = ['central', 'tenant'];
}
