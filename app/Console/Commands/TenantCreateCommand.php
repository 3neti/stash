<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create 
                            {name : The tenant name}
                            {--slug= : Custom slug (auto-generated if not provided)}
                            {--email= : Tenant contact email}
                            {--domain= : Domain for the tenant}
                            {--skip-migrations : Skip running migrations}';

    protected $description = 'Create a new tenant with database and run migrations';

    public function handle(TenantConnectionManager $manager): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?? \Illuminate\Support\Str::slug($name);
        $email = $this->option('email');
        $domain = $this->option('domain');

        // Validate slug uniqueness
        if (Tenant::on('pgsql')->where('slug', $slug)->exists()) {
            $this->error("Tenant with slug '{$slug}' already exists");
            return self::FAILURE;
        }

        $this->info("Creating tenant: {$name}");

        try {
            // Create tenant record and domain in a transaction
            $tenant = DB::connection('pgsql')->transaction(function () use ($name, $slug, $email, $domain) {
                $tenant = Tenant::on('pgsql')->create([
                    'name' => $name,
                    'slug' => $slug,
                    'email' => $email,
                    'status' => 'active',
                    'tier' => 'starter',
                    'settings' => [],
                    'credit_balance' => 0,
                ]);

                $this->info("✓ Tenant record created (ID: {$tenant->id})");

                // Create domain if provided
                if ($domain) {
                    $tenant->domains()->create([
                        'domain' => $domain,
                        'is_primary' => true,
                    ]);
                    $this->info("✓ Domain created: {$domain}");
                }

                return $tenant;
            });

            // Create tenant database (must be outside transaction)
            $this->info('Creating tenant database...');
            $manager->createTenantDatabase($tenant);
            $dbName = $manager->getTenantDatabaseName($tenant);
            $this->info("✓ Database created: {$dbName}");

            // Run migrations unless skipped
            if (!$this->option('skip-migrations')) {
                $this->info('Running tenant migrations...');
                
                TenantContext::run($tenant, function () {
                    Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                });
                
                $this->info('✓ Migrations completed');
            }

            $this->newLine();
            $this->info("Tenant '{$name}' created successfully!");
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
