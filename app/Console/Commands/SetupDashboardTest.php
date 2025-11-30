<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SetupDashboardTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:setup-test
                            {--fresh : Drop all tables and start fresh}
                            {--tenant-name=Test Company : Name of the test tenant}
                            {--tenant-domain=test.localhost : Domain for the test tenant}';

    /**
     * The console command description.
     */
    protected $description = 'Set up tenant and seed test data for Phase 3.1 Dashboard testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Setting up Dashboard Test Environment...');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh installation requested - dropping all tables...');

            if (! $this->confirm('Are you sure? This will delete ALL data!', false)) {
                $this->error('Setup cancelled.');

                return 1;
            }

            $this->runFreshMigrations();
        }

        $manager = app(TenantConnectionManager::class);
        $tenant = $this->setupTenant($manager);

        if (! $tenant) {
            $this->error('Failed to create tenant.');

            return 1;
        }

        $this->runTenantMigrations($tenant);
        $this->seedTenantData($tenant);
        $this->createTestUser($tenant);

        $this->newLine();
        $this->info('✅ Dashboard test environment ready!');
        $this->newLine();
        $this->displayInstructions();

        return 0;
    }

    /**
     * Run fresh migrations on central database.
     */
    private function runFreshMigrations(): void
    {
        $this->info('📦 Running fresh migrations on central database...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info('✓ Central database migrated');
    }

    /**
     * Set up test tenant.
     */
    private function setupTenant(TenantConnectionManager $manager): ?object
    {
        $name = $this->option('tenant-name');
        $domain = $this->option('tenant-domain');

        $this->info("🏢 Creating tenant: {$name}");

        try {
            $slug = str($name)->slug();
            $existingTenant = DB::connection('pgsql')
                ->table('tenants')
                ->where('slug', $slug)
                ->first();

            if ($existingTenant) {
                $this->warn('⚠️  Tenant already exists');

                if ($this->confirm('Use existing tenant?', true)) {
                    $this->info('✓ Using existing tenant');

                    // Check if tenant database exists
                    $tenant = \App\Models\Tenant::on('pgsql')->find($existingTenant->id);
                    if (! $manager->tenantDatabaseExists($tenant)) {
                        $this->info('Creating missing tenant database...');
                        $manager->createTenantDatabase($tenant);
                    }

                    return $existingTenant;
                }

                $this->error('Setup cancelled.');

                return null;
            }

            Artisan::call('tenant:create', [
                'name' => $name,
                '--domain' => $domain,
            ]);

            $tenant = DB::connection('pgsql')
                ->table('tenants')
                ->where('slug', $slug)
                ->first();

            $this->info("✓ Tenant created: {$tenant->slug}");
            $this->info("  Domain: {$domain}");

            return $tenant;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Run migrations on tenant database.
     */
    private function runTenantMigrations(object $tenant): void
    {
        $this->info('📦 Running tenant migrations...');

        try {
            $tenantModel = \App\Models\Tenant::on('pgsql')->find($tenant->id);

            TenantContext::run($tenantModel, function () {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            });

            $this->info('✓ Tenant database migrated');
        } catch (\Exception $e) {
            $this->error("Failed to run tenant migrations: {$e->getMessage()}");
        }
    }

    /**
     * Seed tenant with test data.
     */
    private function seedTenantData(object $tenant): void
    {
        $this->info('🌱 Seeding test data...');

        try {
            $tenantModel = \App\Models\Tenant::on('pgsql')->find($tenant->id);

            TenantContext::run($tenantModel, function () {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\DashboardTestSeeder',
                    '--database' => 'tenant',
                ]);

                $output = Artisan::output();
                $this->line($output);
            });
        } catch (\Exception $e) {
            $this->error("Failed to seed data: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
        }
    }

    /**
     * Create test user in central database.
     */
    private function createTestUser(object $tenant): void
    {
        $this->info('👤 Creating test user...');

        try {
            // Check if user exists by email (regardless of tenant)
            $existingUser = \App\Models\User::on('pgsql')
                ->where('email', 'test@example.com')
                ->first();

            if ($existingUser) {
                // Update existing user to link to tenant
                if ($existingUser->tenant_id === $tenant->id) {
                    $this->info('✓ Test user already exists with correct tenant');

                    return;
                }

                $this->warn('⚠️  Test user exists but linked to different/no tenant');
                $this->info('   Updating user to link to current tenant...');

                $existingUser->tenant_id = $tenant->id;
                $existingUser->role = 'owner';
                $existingUser->email_verified_at = now();
                $existingUser->save();

                $this->info('✓ Test user updated');

                return;
            }

            // Create new user in central database linked to tenant
            \App\Models\User::on('pgsql')->create([
                'tenant_id' => $tenant->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'owner',
            ]);

            $this->info('✓ Test user created');
        } catch (\Exception $e) {
            $this->error("Failed to create test user: {$e->getMessage()}");
        }
    }

    /**
     * Display usage instructions.
     */
    private function displayInstructions(): void
    {
        $domain = $this->option('tenant-domain');

        $this->components->twoColumnDetail('📝 Login URL', 'http://stash.test');
        $this->components->twoColumnDetail('📧 Email', 'test@example.com');
        $this->components->twoColumnDetail('🔑 Password', 'password');
        $this->newLine();
        $this->info('🚦 Start development server:');
        $this->line('  composer run dev');
        $this->newLine();
        $this->info('🌐 Or start services individually:');
        $this->line('  php artisan serve');
        $this->line('  npm run dev');
        $this->newLine();

        if ($domain !== 'localhost') {
            $this->warn('💡 Tip: Add to /etc/hosts for custom domain:');
            $this->line("  127.0.0.1 {$domain}");
            $this->line("  Then visit: http://{$domain}:8000");
        }
    }
}
