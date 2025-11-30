<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrateCommand extends Command
{
    protected $signature = 'tenant:migrate 
                            {tenant? : Tenant ID to migrate (migrates all if not provided)}
                            {--fresh : Drop all tables before running migrations}
                            {--seed : Run seeders after migrations}';

    protected $description = 'Run migrations for one or all tenants';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');

        $tenants = $tenantId
            ? [Tenant::on('pgsql')->findOrFail($tenantId)]
            : Tenant::on('pgsql')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found');

            return self::SUCCESS;
        }

        $this->info("Migrating {$tenants->count()} tenant(s)...");
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->name} (ID: {$tenant->id})");

            try {
                TenantContext::run($tenant, function () {
                    $options = [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ];

                    if ($this->option('fresh')) {
                        Artisan::call('migrate:fresh', $options);
                        $this->line('  ✓ Fresh migration completed');
                    } else {
                        Artisan::call('migrate', $options);
                        $this->line('  ✓ Migration completed');
                    }

                    if ($this->option('seed')) {
                        Artisan::call('db:seed', [
                            '--database' => 'tenant',
                            '--force' => true,
                        ]);
                        $this->line('  ✓ Seeding completed');
                    }
                });
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('All migrations completed!');

        return self::SUCCESS;
    }
}
