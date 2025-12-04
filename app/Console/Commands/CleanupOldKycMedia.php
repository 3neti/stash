<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ProcessorExecution;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Clean up old KYC media from ProcessorExecutions after retention period.
 * 
 * This command:
 * 1. Finds ProcessorExecutions older than retention period (default: 30 days)
 * 2. Verifies Contact has copied media (safety check)
 * 3. Deletes media from ProcessorExecution (preserves Contact media)
 * 
 * Usage:
 *   php artisan kyc:cleanup-old-media
 *   php artisan kyc:cleanup-old-media --days=60 --dry-run
 *   php artisan kyc:cleanup-old-media --tenant=01abc123...
 */
class CleanupOldKycMedia extends Command
{
    protected $signature = 'kyc:cleanup-old-media
                            {--days=30 : Retention period in days}
                            {--dry-run : Show what would be deleted without deleting}
                            {--tenant= : Clean specific tenant UUID (optional)}';

    protected $description = 'Clean up old KYC media from ProcessorExecutions after retention period';

    public function handle(TenancyService $tenancyService): int
    {
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');
        
        $this->info(sprintf(
            "[%s] Cleaning up KYC media older than %d days...",
            $dryRun ? 'DRY RUN' : 'LIVE',
            $retentionDays
        ));
        
        // Get tenants to process
        $tenants = $tenantId
            ? Tenant::on('central')->where('id', $tenantId)->get()
            : Tenant::on('central')->get();
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found');
            return self::FAILURE;
        }
        
        $totalDeleted = 0;
        $totalSkipped = 0;
        
        foreach ($tenants as $tenant) {
            $tenancyService->initializeTenant($tenant);
            
            $this->line("Processing tenant: {$tenant->name} ({$tenant->id})");
            
            // Find old ProcessorExecutions with media
            $cutoffDate = now()->subDays($retentionDays);
            
            $executions = ProcessorExecution::where('created_at', '<', $cutoffDate)
                ->whereHas('media', function ($query) {
                    $query->whereIn('collection_name', ['kyc_id_cards', 'kyc_selfies']);
                })
                ->with('media')
                ->get();
            
            $this->line("  Found {$executions->count()} old executions with media");
            
            foreach ($executions as $execution) {
                // Find corresponding Contact via KycTransaction
                $kycTransaction = DB::connection('central')
                    ->table('kyc_transactions')
                    ->where('processor_execution_id', $execution->id)
                    ->first();
                
                if (!$kycTransaction) {
                    $this->warn("  Skipping execution {$execution->id}: No KycTransaction found");
                    $totalSkipped++;
                    continue;
                }
                
                $contact = Contact::where('kyc_transaction_id', $kycTransaction->transaction_id)->first();
                
                if (!$contact) {
                    $this->warn("  Skipping execution {$execution->id}: No Contact found");
                    $totalSkipped++;
                    continue;
                }
                
                // Verify Contact has media (safety check)
                $contactHasMedia = $contact->getMedia('kyc_id_cards')->count() > 0
                    || $contact->getMedia('kyc_selfies')->count() > 0;
                
                if (!$contactHasMedia) {
                    $this->warn("  Skipping execution {$execution->id}: Contact has no media");
                    $totalSkipped++;
                    continue;
                }
                
                // Delete media from execution
                $mediaCount = $execution->getMedia('kyc_id_cards')->count()
                    + $execution->getMedia('kyc_selfies')->count();
                
                if (!$dryRun) {
                    $execution->clearMediaCollection('kyc_id_cards');
                    $execution->clearMediaCollection('kyc_selfies');
                    
                    Log::info('[KYC Cleanup] Media deleted from ProcessorExecution', [
                        'execution_id' => $execution->id,
                        'contact_id' => $contact->id,
                        'media_count' => $mediaCount,
                        'age_days' => now()->diffInDays($execution->created_at),
                    ]);
                }
                
                $this->line(sprintf(
                    "  %s: Execution %s (%d media, %d days old)",
                    $dryRun ? 'Would delete' : 'Deleted',
                    $execution->id,
                    $mediaCount,
                    now()->diffInDays($execution->created_at)
                ));
                
                $totalDeleted++;
            }
        }
        
        $this->newLine();
        $this->info(sprintf(
            "%s: %d executions, %d skipped",
            $dryRun ? 'Would delete' : 'Deleted',
            $totalDeleted,
            $totalSkipped
        ));
        
        if ($dryRun) {
            $this->warn('DRY RUN - No changes were made. Run without --dry-run to delete.');
        }
        
        return self::SUCCESS;
    }
}
