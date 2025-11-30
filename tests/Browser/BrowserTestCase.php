<?php

declare(strict_types=1);

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    use DatabaseMigrations;

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
