<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class CampaignListCommand extends Command
{
    protected $signature = 'campaign:list 
                            {--tenant= : Tenant slug (uses first active tenant if not specified)}
                            {--show-processors : Show processor details for each campaign}';

    protected $description = 'List campaigns for a tenant';

    public function handle(): int
    {
        // Find tenant
        $tenantSlug = $this->option('tenant');
        if ($tenantSlug) {
            $tenant = Tenant::on('central')->where('slug', $tenantSlug)->first();
            if (! $tenant) {
                $this->error("Tenant not found: {$tenantSlug}");
                return self::FAILURE;
            }
        } else {
            $tenant = Tenant::on('central')->where('status', 'active')->first();
            if (! $tenant) {
                $this->error('No active tenants found');
                return self::FAILURE;
            }
        }

        $this->info("Campaigns for tenant: {$tenant->name} ({$tenant->slug})");
        $this->newLine();

        // List campaigns in tenant context
        return TenantContext::run($tenant, function () {
            $campaigns = Campaign::orderBy('name')->get();

            if ($campaigns->isEmpty()) {
                $this->warn('No campaigns found for this tenant');
                return self::SUCCESS;
            }

            // Display campaigns table
            $rows = [];
            foreach ($campaigns as $campaign) {
                $processorCount = count($campaign->pipeline_config['processors'] ?? []);
                $rows[] = [
                    $campaign->name,
                    $campaign->slug,
                    $campaign->published_at ? 'Published' : 'Draft',
                    $processorCount,
                    substr($campaign->id, 0, 8) . '...',
                ];
            }

            $this->table(
                ['Name', 'Slug', 'Status', 'Processors', 'ID'],
                $rows
            );

            $this->newLine();
            $this->info("Total: {$campaigns->count()} campaign(s)");

            // Show processor details if requested
            if ($this->option('show-processors')) {
                $this->newLine();
                $this->info('Processor Details:');
                $this->newLine();

                foreach ($campaigns as $campaign) {
                    $this->line("<comment>{$campaign->name} ({$campaign->slug})</comment>");
                    $processors = $campaign->pipeline_config['processors'] ?? [];
                    
                    if (empty($processors)) {
                        $this->line('  No processors configured');
                        continue;
                    }

                    foreach ($processors as $index => $processor) {
                        $name = $processor['name'] ?? 'Unknown';
                        $type = $processor['type'] ?? 'N/A';
                        $id = $processor['id'] ?? 'N/A';
                        $stepId = $processor['step_id'] ?? 'N/A';
                        
                        $this->line("  [{$index}] {$name}");
                        $this->line("      Type: {$type}");
                        $this->line("      ID: {$id}");
                        if ($stepId !== 'N/A') {
                            $this->line("      Step ID: {$stepId}");
                        }
                    }
                    $this->newLine();
                }
            }

            return self::SUCCESS;
        });
    }
}
