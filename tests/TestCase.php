<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Run tenant migrations after central migrations.
     */
    protected function afterRefreshingDatabase()
    {
        // Run tenant migrations on central DB (shared schema for testing)
        Artisan::call('migrate', [
            '--database' => 'pgsql',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
