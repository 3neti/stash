<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantDeleteCommand extends Command
{
    protected $signature = 'tenant:delete 
                            {tenant : Tenant ID to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a tenant and its database (DESTRUCTIVE)';

    public function handle(TenantConnectionManager $manager): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::on('pgsql')->find($tenantId);

        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found");
            return self::FAILURE;
        }

        $this->warn("WARNING: This will permanently delete tenant '{$tenant->name}' and all its data!");
        $this->line("Tenant ID: {$tenant->id}");
        $this->line("Database: {$manager->getTenantDatabaseName($tenant)}");

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to continue?', false)) {
                $this->info('Deletion cancelled');
                return self::SUCCESS;
            }
        }

        try {
            DB::connection('pgsql')->transaction(function () use ($tenant, $manager) {
                // Drop tenant database
                $this->info('Dropping tenant database...');
                $manager->dropTenantDatabase($tenant);
                $this->info('✓ Database dropped');

                // Delete tenant record (soft delete)
                $tenant->delete();
                $this->info('✓ Tenant record deleted');
            });

            $this->newLine();
            $this->info("Tenant '{$tenant->name}' deleted successfully");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to delete tenant: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
