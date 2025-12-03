<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Cleanup Processor Artifacts Command
 *
 * Removes old processor artifacts based on campaign retention policies.
 */
class CleanupProcessorArtifacts extends Command
{
    protected $signature = 'artifacts:cleanup 
                            {--days= : Override days to retain (ignores campaign retention_days)}
                            {--tenant= : Specific tenant slug to cleanup}
                            {--campaign= : Specific campaign slug to cleanup}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Cleanup old processor artifacts based on retention policies';

    public function handle(): int
    {
        $daysToRetain = $this->option('days');
        $tenantSlug = $this->option('tenant');
        $campaignSlug = $this->option('campaign');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->info('🔍 Dry-run mode: Showing what would be deleted...');
            $this->newLine();
        }

        // Get tenants to process
        $tenants = $tenantSlug
            ? [Tenant::on('central')->where('slug', $tenantSlug)->firstOrFail()]
            : Tenant::on('central')->where('status', 'active')->get();

        $totalDeleted = 0;
        $totalSize = 0;

        foreach ($tenants as $tenant) {
            $result = TenantContext::run($tenant, function () use ($campaignSlug, $daysToRetain, $dryRun, $force) {
                return $this->cleanupTenant($campaignSlug, $daysToRetain, $dryRun, $force);
            });

            $totalDeleted += $result['deleted'];
            $totalSize += $result['size'];
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Would delete {$totalDeleted} artifact(s) (" . $this->formatBytes($totalSize) . ')');  
        } else {
            $this->info("✅ Deleted {$totalDeleted} artifact(s) (" . $this->formatBytes($totalSize) . ')');
        }

        return self::SUCCESS;
    }

    private function cleanupTenant(?string $campaignSlug, ?int $daysOverride, bool $dryRun, bool $force): array
    {
        $campaigns = $campaignSlug
            ? [Campaign::where('slug', $campaignSlug)->firstOrFail()]
            : Campaign::all();

        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($campaigns as $campaign) {
            $retentionDays = $daysOverride ?? $campaign->retention_days;
            $cutoffDate = now()->subDays($retentionDays);

            $this->line("Campaign: {$campaign->name} (retention: {$retentionDays} days)");

            // Get media older than cutoff date for this campaign's processor executions
            $query = Media::query()
                ->where('model_type', 'App\\Models\\ProcessorExecution')
                ->where('created_at', '<', $cutoffDate)
                ->whereHas('model', function ($q) use ($campaign) {
                    $q->whereHas('documentJob', function ($jobQuery) use ($campaign) {
                        $jobQuery->where('campaign_id', $campaign->id);
                    });
                });

            $mediaItems = $query->get();
            $count = $mediaItems->count();
            $size = $mediaItems->sum('size');

            if ($count === 0) {
                $this->line("  No artifacts to cleanup");
                continue;
            }

            $this->line("  Found {$count} artifact(s) (" . $this->formatBytes($size) . ') older than ' . $cutoffDate->format('Y-m-d'));

            if ($dryRun) {
                // Show sample of what would be deleted
                foreach ($mediaItems->take(5) as $media) {
                    $this->line("    - {$media->file_name} ({$media->collection_name}, " . $this->formatBytes($media->size) . ')');
                }
                if ($count > 5) {
                    $this->line("    ... and " . ($count - 5) . ' more');
                }
            } else {
                // Confirm deletion unless --force
                if (! $force && ! $this->confirm("  Delete {$count} artifact(s) for this campaign?", true)) {
                    $this->line("  Skipped");
                    continue;
                }

                // Delete media (cascades to files)
                foreach ($mediaItems as $media) {
                    $media->delete();
                }

                $this->line("  ✓ Deleted {$count} artifact(s)");
            }

            $deletedCount += $count;
            $deletedSize += $size;
        }

        return [
            'deleted' => $deletedCount,
            'size' => $deletedSize,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
