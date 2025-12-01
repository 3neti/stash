<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe all tenant databases and tenant records.
 *
 * Useful for complete cleanup during development/testing.
 */
class TenantWipeCommand extends Command
{
    protected $signature = 'tenant:wipe 
                            {--force : Skip confirmation prompt}
                            {--keep-records : Delete databases but keep tenant records}';

    protected $description = 'Wipe all tenant databases and optionally tenant records';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirmWithWarning()) {
            $this->error('Operation cancelled.');
            return self::FAILURE;
        }

        $manager = app(TenantConnectionManager::class);
        $tenants = Tenant::all();

        $this->info("Found {$tenants->count()} tenants to wipe");

        $deletedCount = 0;
        foreach ($tenants as $tenant) {
            try {
                $dbName = $manager->getTenantDatabaseName($tenant);
                $this->line("Dropping database: {$dbName}");
                $manager->dropTenantDatabase($tenant);
                $deletedCount++;
            } catch (\Exception $e) {
                $this->warn("Failed to drop database for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("✓ Dropped {$deletedCount} tenant databases");

        if (! $this->option('keep-records')) {
            $this->info('Deleting tenant records from central database...');
            $count = Tenant::count();
            Tenant::truncate();
            $this->info("✓ Deleted {$count} tenant records");
        }

        $this->info('✅ Tenant wipe complete!');
        return self::SUCCESS;
    }

    /**
     * Show confirmation with warning.
     */
    private function confirmWithWarning(): bool
    {
        $this->warn('⚠️  WARNING: This will permanently delete ALL tenant databases!');
        $this->warn('This action CANNOT be undone.');
        $this->newLine();

        return $this->confirm('Are you absolutely sure you want to continue?', false);
    }
}
