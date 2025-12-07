<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Campaigns\ApplyDefaultTemplates;
use App\Events\TenantOnboarded;
use App\Events\TenantOnboardingFailed;
use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantOnboardingService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private TenantConnectionManager $connectionManager,
    ) {}

    /**
     * Onboard a new tenant with database, migrations, and default templates.
     */
    public function onboard(Tenant $tenant): void
    {
        try {
            Log::info("Starting onboarding for tenant: {$tenant->name} (ID: {$tenant->id})");

            // 1. Create tenant database
            $this->createTenantDatabase($tenant);

            // 2. Run tenant migrations
            $this->runTenantMigrations($tenant);

            // 3. Seed processors (needed by templates)
            $this->seedProcessors($tenant);

            // 4. Apply default campaign templates
            $this->applyDefaultTemplates($tenant);

            // 4. Fire success event
            TenantOnboarded::dispatch($tenant);

            Log::info("Tenant onboarding completed successfully: {$tenant->name}");
        } catch (Throwable $e) {
            Log::error("Tenant onboarding failed: {$tenant->name}", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark tenant as suspended on failure
            $tenant->update(['status' => 'suspended']);

            // Fire failure event
            TenantOnboardingFailed::dispatch($tenant, $e);

            throw $e;
        }
    }

    /**
     * Create the tenant database.
     */
    private function createTenantDatabase(Tenant $tenant): void
    {
        Log::info("Creating database for tenant: {$tenant->name}");
        
        $this->connectionManager->createTenantDatabase($tenant);
        $dbName = $this->connectionManager->getTenantDatabaseName($tenant);
        
        Log::info("Database created: {$dbName}");
    }

    /**
     * Run tenant migrations.
     */
    private function runTenantMigrations(Tenant $tenant): void
    {
        Log::info("Running migrations for tenant: {$tenant->name}");

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });

        Log::info("Migrations completed for tenant: {$tenant->name}");
    }

    /**
     * Seed processors needed by campaign templates.
     */
    private function seedProcessors(Tenant $tenant): void
    {
        Log::info("Seeding processors for tenant: {$tenant->name}");

        TenantContext::run($tenant, function () {
            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--force' => true,
            ]);
        });

        Log::info("Processors seeded for tenant: {$tenant->name}");
    }

    /**
     * Apply default campaign templates.
     */
    private function applyDefaultTemplates(Tenant $tenant): void
    {
        $templates = config('campaigns.default_templates', []);

        if (empty($templates)) {
            Log::info("No default templates configured, skipping template application");
            return;
        }

        Log::info("Applying default templates for tenant: {$tenant->name}", [
            'templates' => $templates,
        ]);

        ApplyDefaultTemplates::run($tenant);

        Log::info("Default templates applied for tenant: {$tenant->name}");
    }
}
