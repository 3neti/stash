<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create tenants first using: php artisan tenant:create');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->command->info("\nSeeding tenant: {$tenant->name} ({$tenant->id})");

            TenantContext::run($tenant, function () {
                $this->call([
                    ProcessorSeeder::class,
                    CredentialSeeder::class,
                    CampaignSeeder::class,
                    DemoDataSeeder::class,
                ]);
            });
        }

        $this->command->info("\n✅ Database seeding completed for ".$tenants->count().' tenant(s)');
    }
}
