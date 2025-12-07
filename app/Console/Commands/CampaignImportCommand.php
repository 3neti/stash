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
                            {file? : Path to JSON or YAML campaign definition}
                            {--stdin : Read campaign definition from STDIN}
                            {--json= : JSON string containing campaign definition}
                            {--tenant= : Tenant ID (required)}
                            {--validate-only : Validate without creating}';

    protected $description = 'Import campaign from file, STDIN, or JSON string';

    public function handle(ProcessorRegistry $registry): int
    {
        $tenantId = $this->option('tenant');
        $validateOnly = $this->option('validate-only');

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

        // Parse input from multiple sources
        try {
            $data = $this->parseInput();
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
     * Parse input from multiple sources (priority: --json > --stdin > file).
     */
    private function parseInput(): array
    {
        // Priority 1: --json option
        if ($json = $this->option('json')) {
            return $this->parseJson($json);
        }

        // Priority 2: --stdin flag
        if ($this->option('stdin')) {
            return $this->parseStdin();
        }

        // Priority 3: file argument
        if ($filePath = $this->argument('file')) {
            return $this->parseFile($filePath);
        }

        throw new \RuntimeException(
            'No input provided. Use a file argument, --stdin flag, or --json option.'
        );
    }

    /**
     * Parse JSON string.
     */
    private function parseJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Parse input from STDIN.
     */
    private function parseStdin(): array
    {
        $content = '';
        while (! feof(STDIN)) {
            $content .= fread(STDIN, 8192);
        }

        if (empty($content)) {
            throw new \RuntimeException('No data received from STDIN.');
        }

        // Try JSON first
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try YAML
        try {
            return Yaml::parse($content);
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid JSON or YAML from STDIN: '.$e->getMessage());
        }
    }

    /**
     * Parse JSON or YAML file.
     */
    private function parseFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (in_array($extension, ['yaml', 'yml'])) {
            return Yaml::parseFile($filePath);
        }

        if ($extension === 'json') {
            $content = file_get_contents($filePath);

            return $this->parseJson($content);
        }

        throw new \RuntimeException("Unsupported file format: {$extension}. Use .json, .yaml, or .yml");
    }
}
