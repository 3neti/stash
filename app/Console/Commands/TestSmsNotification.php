<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Notifications\DocumentProcessedNotification;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
class TestSmsNotification extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notification:test-sms
                            {--tenant= : Tenant ID (optional, defaults to first tenant)}
                            {--campaign= : Campaign ID or slug (optional, defaults to first campaign)}
                            {--mobile= : Mobile number to send SMS to (required for SMS)}
                            {--document= : Document ID (optional, will create test document if not provided)}
                            {--sync : Send notification synchronously (bypass queue)}';

    /**
     * The console command description.
     */
    protected $description = 'Test SMS notification system using anonymous notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📱 Testing SMS Notification System (Anonymous Notifications)');
        $this->newLine();

        // Step 1: Get tenant
        $tenantInput = $this->option('tenant');
        if ($tenantInput) {
            $tenant = Tenant::find($tenantInput);
        } else {
            $tenant = Tenant::first();
            $this->comment('Using first tenant');
        }

        if (! $tenant) {
            $this->error('❌ Tenant not found');
            return self::FAILURE;
        }

        $this->info("✓ Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->newLine();

        // Step 2: Initialize tenant context and run everything inside it
        return TenantContext::run($tenant, function () {
            // Step 3: Get campaign (now we're in tenant context)
            $campaignInput = $this->option('campaign');
            if ($campaignInput) {
                $campaign = $this->getCampaign($campaignInput);
            } else {
                $campaign = Campaign::first();
                $this->comment('Using first campaign');
            }

            if (! $campaign) {
                $this->error('❌ Campaign not found in tenant database');
                return self::FAILURE;
            }

            $this->info("✓ Campaign: {$campaign->name} (ID: {$campaign->id})");

            // Step 4: Get mobile number from option or campaign settings
            $mobile = $this->option('mobile') ?? $campaign->notification_settings['sms_mobile'] ?? null;

            if (! $mobile) {
                $this->warn('⚠ No mobile number provided');
                $this->newLine();
                $this->comment('Provide mobile via:');
                $this->line('  --mobile=09173011987');
                $this->line('  OR set in campaign notification_settings["sms_mobile"]');
                $this->newLine();

                if (! $this->confirm('Continue without SMS? (Database notification only)', false)) {
                    return self::FAILURE;
                }
            } else {
                $this->info("✓ Mobile: {$mobile}");
            }

            $this->newLine();

            // Step 5: Check campaign notification settings
            $notificationSettings = $campaign->notification_settings ?? [];
            $channels = $notificationSettings['channels'] ?? ['database'];

            $this->info('✓ Notification Channels: '.implode(', ', $channels));

            if (! in_array('sms', $channels, true)) {
                $this->warn('⚠ SMS channel not enabled for this campaign');
                $this->newLine();

                if ($this->confirm('Enable SMS channel now?', true)) {
                    $notificationSettings['channels'] = array_unique([...$channels, 'sms']);
                    $notificationSettings['sms_provider'] = 'txtcmdr';
                    $channels = $notificationSettings['channels'];
                    $campaign->notification_settings = $notificationSettings;
                    $campaign->save();

                    $this->info('✓ SMS channel enabled');
                }
            }

            $this->newLine();

            // Step 6: Get or create document
            $documentId = $this->option('document');
            if ($documentId) {
                $document = Document::find($documentId);
                if (! $document) {
                    $this->error('❌ Document not found in tenant database');
                    return self::FAILURE;
                }
            } else {
                // Create test document
                $document = new Document([
                    'filename' => 'test-document-'.now()->format('YmdHis').'.pdf',
                    'original_filename' => 'test-document.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024,
                    'storage_path' => 'test/path/document.pdf',
                    'hash' => hash('sha256', 'test-content'),
                ]);
                $document->campaign_id = $campaign->id;
                $document->save();

                $this->info("✓ Created test document: {$document->filename}");
            }

            $this->newLine();

            // Step 7: Send notification using anonymous routing
            try {
                $sync = $this->option('sync');
                
                if ($sync) {
                    $this->info('📤 Sending notification synchronously (bypassing queue)...');
                } else {
                    $this->info('📤 Sending notification via queue...');
                    $this->comment('💡 Tip: Use --sync flag to send immediately without queue');
                }
                
                // Build routes for anonymous notification
                $routes = [];
                if (in_array('sms', $channels, true) && $mobile) {
                    $routes['sms'] = $mobile;
                }
                
                // Create notification instance
                $notification = new DocumentProcessedNotification($document, $campaign);
                
                // Send via anonymous notification
                if ($sync) {
                    // Force immediate execution (no queue)
                    \Illuminate\Support\Facades\Notification::sendNow(
                        \Illuminate\Support\Facades\Notification::routes($routes),
                        $notification
                    );
                } else {
                    // Queue the notification (default)
                    \Illuminate\Support\Facades\Notification::routes($routes)
                        ->notify($notification);
                }

                $this->newLine();
                $this->info('✅ Notification sent successfully!');
                $this->newLine();

                $this->table(
                    ['Channel', 'Status'],
                    collect($channels)->map(fn ($channel) => [
                        $channel,
                        $channel === 'sms' && ! $mobile ? 'Skipped (no mobile)' : 'Sent',
                    ])->toArray()
                );

                if (in_array('database', $channels, true)) {
                    $this->newLine();
                    $this->comment('💡 Note: Database channel only works with notifiable models');
                    $this->comment('   Anonymous notifications skip database storage');
                }

                if (in_array('sms', $channels, true) && $mobile) {
                    $this->newLine();
                    $this->comment('📱 Check mobile '.$mobile.' for SMS message');
                }

                return self::SUCCESS;

            } catch (\Exception $e) {
                $this->newLine();
                $this->error('❌ Failed to send notification: '.$e->getMessage());

                if ($this->output->isVerbose()) {
                    $this->line($e->getTraceAsString());
                }

                return self::FAILURE;
            }
        });
    }

    /**
     * Get campaign by ID or slug (tenant context)
     */
    protected function getCampaign(string $input): ?Campaign
    {
        if (is_numeric($input)) {
            return Campaign::find($input);
        }

        return Campaign::where('slug', $input)->first();
    }
}
