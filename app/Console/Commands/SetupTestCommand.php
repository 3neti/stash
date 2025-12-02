<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:setup-test';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Setup test database with migrations and seeders for browser testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Setting up test environment for browser testing...');

        try {
            // Run fresh migrations for testing environment
            $this->info('Running migrations...');
            Artisan::call('migrate:fresh', [
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $this->info('✓ Migrations completed');

            // Create a test tenant if none exist
            $this->info('Checking tenants...');
            $tenantCount = \App\Models\Tenant::count();
            if ($tenantCount === 0) {
                $this->info('Creating test tenant...');
                Artisan::call('tenant:create', [
                    'name' => 'Test Company',
                    '--email' => 'test@company.test',
                    '--no-interaction' => true,
                ]);
                $this->info('✓ Test tenant created');
            } else {
                $this->info("✓ Found {$tenantCount} existing tenant(s)");
            }

            // Seed the test database
            $this->info('Seeding test database...');
            Artisan::call('db:seed', [
                '--env' => 'testing',
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $this->info('✓ Database seeded');

            $this->newLine();
            $this->info('✅ Test environment ready!');
            $this->info('Test user credentials:');
            $this->line('  Email: test@example.com');
            $this->line('  Password: password');
            $this->newLine();
            $this->info('Run browser tests with:');
            $this->line('  php artisan test tests/Browser/');
            $this->newLine();

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Setup failed: '.$e->getMessage());

            return 1;
        }
    }
}
