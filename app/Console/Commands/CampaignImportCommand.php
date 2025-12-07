<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Campaigns\ImportCampaign;
use App\Data\Campaigns\CampaignImportData;
use App\Models\Tenant;
use App\Services\Pipeline\ProcessorRegistry;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class CampaignImportCommand extends Command
{
    protected $signature = 'campaign:import 
                            {file : Path to JSON or YAML campaign definition}
                            {--tenant= : Tenant ID (required)}
                            {--validate-only : Validate without creating}';

    protected $description = 'Import campaign from JSON or YAML file';

    public function handle(ProcessorRegistry $registry): int
    {
        $filePath = $this->argument('file');
        $tenantId = $this->option('tenant');
        $validateOnly = $this->option('validate-only');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        // Require tenant ID
        if (! $tenantId) {
            $this->error('Tenant ID required. Use --tenant=<id>');

            return self::FAILURE;
        }

        // Find tenant
        $tenant = Tenant::on('central')->find($tenantId);
        if (! $tenant) {
            $this->error("Tenant not found: {$tenantId}");

            return self::FAILURE;
        }

        // Parse file
        try {
            $data = $this->parseFile($filePath);
        } catch (\Exception $e) {
            $this->error("Parse error: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Convert to DTO (validates automatically)
        try {
            $dto = CampaignImportData::from($data);
        } catch (\Exception $e) {
            $this->error('Validation failed:');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if ($validateOnly) {
            $this->info('✓ Validation passed! Campaign definition is valid.');

            return self::SUCCESS;
        }

        // Import campaign within tenant context
        try {
            $campaign = TenantContext::run($tenant, function () use ($dto, $registry) {
                return ImportCampaign::run($dto, $registry);
            });

            $this->info('✓ Campaign imported successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $campaign->id],
                    ['Name', $campaign->name],
                    ['Slug', $campaign->slug],
                    ['Type', $campaign->type],
                    ['State', class_basename($campaign->state)],
                    ['Processors', count($campaign->pipeline_config['processors'] ?? [])],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Parse JSON or YAML file.
     */
    private function parseFile(string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (in_array($extension, ['yaml', 'yml'])) {
            return Yaml::parseFile($filePath);
        }

        if ($extension === 'json') {
            $content = file_get_contents($filePath);
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: '.json_last_error_msg());
            }

            return $decoded;
        }

        throw new \RuntimeException("Unsupported file format: {$extension}. Use .json, .yaml, or .yml");
    }
}
