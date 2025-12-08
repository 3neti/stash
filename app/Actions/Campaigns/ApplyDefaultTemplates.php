<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Apply default campaign templates to a new tenant.
 *
 * Templates are JSON/YAML files in campaigns/templates/ directory.
 */
class ApplyDefaultTemplates
{
    use AsAction;

    /**
     * Apply templates to tenant.
     *
     * @param  Tenant  $tenant  Tenant to apply templates to
     * @param  array|null  $templates  Template slugs, or null to use config
     * @return Collection<Campaign> Created campaigns
     */
    public function handle(Tenant $tenant, ?array $templates = null): Collection
    {
        $templates = $templates ?? config('campaigns.default_templates', []);
        $campaigns = collect();

        foreach ($templates as $templateSlug) {
            try {
                $campaign = $this->applyTemplate($tenant, $templateSlug);
                if ($campaign) {
                    $campaigns->push($campaign);
                }
            } catch (\Exception $e) {
                Log::error("Failed to apply template '{$templateSlug}' to tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        return $campaigns;
    }

    /**
     * Apply single template to tenant.
     */
    private function applyTemplate(Tenant $tenant, string $templateSlug): ?Campaign
    {
        $templatePath = $this->findTemplate($templateSlug);

        if (! $templatePath) {
            Log::warning("Template not found: {$templateSlug}");

            return null;
        }

        return TenantContext::run($tenant, function () use ($templatePath, $tenant, $templateSlug) {
            // Import campaign from template file
            $exitCode = Artisan::call('campaign:import', [
                'file' => $templatePath,
                '--tenant' => $tenant->id,
            ]);
            
            $output = Artisan::output();
            
            if ($exitCode !== 0) {
                Log::error("Campaign import failed for template '{$templateSlug}'", [
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'template_path' => $templatePath,
                    'tenant_id' => $tenant->id,
                ]);
                throw new \RuntimeException("Campaign import failed: {$output}");
            }
            
            Log::debug("Campaign import succeeded for template '{$templateSlug}'", [
                'output' => $output,
            ]);

            // Get the created campaign (assumes slug matches template slug)
            return Campaign::where('slug', $templateSlug)->first();
        });
    }

    /**
     * Find template file by slug.
     */
    private function findTemplate(string $slug): ?string
    {
        $basePath = base_path('campaigns/templates');

        // Try JSON first, then YAML
        $paths = [
            "{$basePath}/{$slug}.json",
            "{$basePath}/{$slug}.yaml",
            "{$basePath}/{$slug}.yml",
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
