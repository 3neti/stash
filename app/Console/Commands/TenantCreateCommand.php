<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create 
                            {name : The tenant name}
                            {--slug= : Custom slug (auto-generated if not provided)}
                            {--email= : Tenant contact email}
                            {--domain= : Domain for the tenant}';

    protected $description = 'Create a new tenant (auto-onboarding via observer)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?? \Illuminate\Support\Str::slug($name);
        $email = $this->option('email');
        $domain = $this->option('domain');

        // Validate slug uniqueness
        if (Tenant::on('central')->where('slug', $slug)->exists()) {
            $this->error("Tenant with slug '{$slug}' already exists");

            return self::FAILURE;
        }

        $this->info("Creating tenant: {$name}");

        try {
            // Create tenant record and domain in a transaction
            // Observer will automatically handle database creation, migrations, and templates
            $tenant = DB::connection('central')->transaction(function () use ($name, $slug, $email, $domain) {
                $tenant = Tenant::on('central')->create([
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

            $this->newLine();
            $this->info("Tenant '{$name}' created successfully!");
            
            if (config('app.tenant_auto_onboarding', true)) {
                $this->info('⏳ Onboarding in progress (database, migrations, templates)...');
                $this->info('💡 Check logs for onboarding status');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
