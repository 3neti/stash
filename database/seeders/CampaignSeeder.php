<?php

namespace Database\Seeders;

use App\Actions\Campaigns\ApplyDefaultTemplates;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Campaign Seeder
 *
 * NOTE: Campaigns are automatically created for new tenants via TenantObserver.
 * This seeder only backfills campaigns for existing tenants that don't have any.
 *
 * Campaign definitions are in campaigns/templates/ directory.
 * Control which templates are applied via DEFAULT_CAMPAIGN_TEMPLATES in .env
 */
class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // If running in tenant context, backfill if needed
        if (TenantContext::isInitialized()) {
            $campaignCount = Campaign::count();
            if ($campaignCount > 0) {
                $this->command->info("Tenant already has {$campaignCount} campaigns (auto-created by observer). Skipping.");
                return;
            }

            // Backfill for existing tenant without campaigns
            $this->backfillCampaigns(TenantContext::current());
            return;
        }

        // Loop through all tenants and backfill if needed
        $tenants = Tenant::on('central')->get();
        foreach ($tenants as $tenant) {
            TenantContext::run($tenant, function () use ($tenant) {
                $campaignCount = Campaign::count();
                if ($campaignCount > 0) {
                    $this->command->info("Tenant {$tenant->slug} already has {$campaignCount} campaigns. Skipping.");
                    return;
                }

                $this->backfillCampaigns($tenant);
            });
        }
    }

    /**
     * Backfill campaigns for existing tenant without any campaigns.
     */
    private function backfillCampaigns(Tenant $tenant): void
    {
        $this->command->info("Backfilling campaigns for tenant: {$tenant->slug}");
        ApplyDefaultTemplates::run($tenant);
        $this->command->info("✓ Applied default templates");
    }
}
