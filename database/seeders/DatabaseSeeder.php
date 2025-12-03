<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // Seed central database users (includes admin from .env)
        $this->call([UserSeeder::class]);

        // Seed custom validation rules (central database)
        $this->call([CustomValidationRuleSeeder::class]);

        $tenants = Tenant::all();

        // Auto-create default tenant if none exist and configured in .env
        if ($tenants->isEmpty()) {
            $tenantName = env('DEFAULT_TENANT_NAME');
            $tenantSlug = env('DEFAULT_TENANT_SLUG');
            $tenantEmail = env('DEFAULT_TENANT_EMAIL');

            if ($tenantName && $tenantSlug) {
                $this->command->info("\nNo tenants found. Creating default tenant from .env...");

                $tenant = Tenant::on('central')->create([
                    'name' => $tenantName,
                    'slug' => $tenantSlug,
                    'email' => $tenantEmail,
                    'status' => 'active',
                    'tier' => 'starter',
                    'settings' => [],
                    'credit_balance' => 0,
                ]);

                $this->command->info("✓ Tenant created: {$tenant->name} (ID: {$tenant->id})");

                // Create tenant database
                $manager = app(TenantConnectionManager::class);
                $manager->createTenantDatabase($tenant);
                $dbName = $manager->getTenantDatabaseName($tenant);
                $this->command->info("✓ Database created: {$dbName}");

                // Run tenant migrations
                $this->command->info('Running tenant migrations...');
                TenantContext::run($tenant, function () {
                    Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                });
                $this->command->info('✓ Tenant migrations completed');

                // Link admin user to tenant
                $adminEmail = env('ADMIN_EMAIL');
                if ($adminEmail) {
                    $admin = User::on('central')->where('email', $adminEmail)->first();
                    if ($admin) {
                        $admin->update(['tenant_id' => $tenant->id]);
                        $this->command->info('✓ Admin user linked to tenant');
                    }
                }

                $tenants = Tenant::all();
            } else {
                $this->command->error('No tenants found. Please configure DEFAULT_TENANT_NAME and DEFAULT_TENANT_SLUG in .env or create tenants using: php artisan tenant:create');

                return;
            }
        }

        foreach ($tenants as $tenant) {
            $this->command->info("\nSeeding tenant: {$tenant->name} ({$tenant->id})");

            TenantContext::run($tenant, function () {
                $this->call([
                    ProcessorSeeder::class,
                    CredentialSeeder::class,
                    CampaignSeeder::class,
                    // DemoDataSeeder::class, // TODO: Update to use state machine
                ]);
            });
        }

        $this->command->info("\n✅ Database seeding completed for ".$tenants->count().' tenant(s)');
    }
}
