<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\UploadDocument;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class TestDocumentUpload extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:upload-document
                            {campaign_id : The campaign ID}
                            {file_path : Path to the document file}
                            {--debug : Show detailed debug output}';

    /**
     * The console command description.
     */
    protected $description = 'Test document upload for a specific campaign';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $campaignId = $this->argument('campaign_id');
        $filePath = $this->argument('file_path');
        $verbose = $this->option('debug');

        $this->info('🚀 Testing Document Upload');
        $this->newLine();

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return 1;
        }

        $this->info("📄 File: {$filePath}");
        $this->info("📦 File size: " . number_format(filesize($filePath)) . " bytes");
        $this->newLine();

        try {
            // Step 1: Load tenant first (to get DB connection)
            $this->info('Step 1: Loading tenant...');
            $tenant = Tenant::on('central')->first();
            
            if (!$tenant) {
                $this->error("❌ No tenants found");
                return 1;
            }

            $this->info("✓ Tenant found: {$tenant->name}");
            $this->newLine();

            // Step 2: Load campaign from tenant context
            $this->info('Step 2: Loading campaign from tenant database...');
            
            $campaign = null;
            TenantContext::run($tenant, function () use ($campaignId, &$campaign) {
                $campaign = Campaign::find($campaignId);
            });
            
            if (!$campaign) {
                $this->error("❌ Campaign not found: {$campaignId}");
                return 1;
            }

            $this->info("✓ Campaign found: {$campaign->name}");
            $this->newLine();

            // Step 3: Initialize tenant context and proceed with upload
            $this->info('Step 3: Initializing tenant context...');
            TenantContext::run($tenant, function () use ($campaign, $filePath, $verbose) {
                $this->info("✓ Tenant context initialized");
                $this->info("  Current tenant: " . TenantContext::current()?->id);
                $this->info("  Default connection: " . \Illuminate\Support\Facades\DB::getDefaultConnection());
                $this->newLine();

                // Step 4: Create fake uploaded file
                $this->info('Step 4: Creating upload file...');
                $filename = basename($filePath);
                $mimeType = mime_content_type($filePath);
                
                // Create UploadedFile from real file
                $uploadedFile = new UploadedFile(
                    $filePath,
                    $filename,
                    $mimeType,
                    null,
                    true  // test mode
                );

                $this->info("✓ Upload file created");
                $this->info("  Name: {$filename}");
                $this->info("  MIME: {$mimeType}");
                $this->newLine();

                // Step 5: Upload document
                $this->info('Step 5: Uploading document...');
                
                if ($verbose) {
                    $this->info('[Debug] Starting upload');
                    \Illuminate\Support\Facades\Log::debug('[TestCommand] Starting upload', [
                        'campaign_id' => $campaign->id,
                        'filename' => $filename,
                    ]);
                }

                try {
                    $action = app(UploadDocument::class);
                    $document = $action->handle($campaign, $uploadedFile);

                    $this->info("✓ Document uploaded successfully!");
                    $this->info("  Document ID: {$document->id}");
                    $this->info("  Document UUID: {$document->uuid}");
                    $this->info("  Storage path: {$document->storage_path}");
                    $this->newLine();

                    // Step 6: Verify document in database
                    $this->info('Step 6: Verifying document in database...');
                    $dbDocument = \App\Models\Document::find($document->id);
                    
                    if ($dbDocument) {
                        $this->info("✓ Document found in database");
                        $this->info("  Campaign ID: {$dbDocument->campaign_id}");
                        $this->info("  State: {$dbDocument->state}");
                        $this->newLine();
                    } else {
                        $this->error("❌ Document not found in database after upload");
                        return 1;
                    }

                    // Step 7: Check if job was created
                    $this->info('Step 7: Checking document job...');
                    $job = \App\Models\DocumentJob::where('document_id', $document->id)->first();
                    
                    if ($job) {
                        $this->info("✓ Document job created");
                        $this->info("  Job ID: {$job->id}");
                        $this->info("  Job UUID: {$job->uuid}");
                        $this->info("  Current processor index: {$job->current_processor_index}");
                        $this->newLine();
                    } else {
                        $this->warn("⚠️  No document job found (may be queued)");
                        $this->newLine();
                    }

                    $this->info('✅ All steps completed successfully!');
                    
                } catch (\Throwable $e) {
                    $this->error("❌ Upload failed!");
                    $this->error("  Exception: " . get_class($e));
                    $this->error("  Message: " . $e->getMessage());
                    
                    if ($verbose) {
                        $this->error("  Stack trace:");
                        $this->error($e->getTraceAsString());
                    }
                    
                    return 1;
                }
            });

            return 0;

        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            
            if ($verbose) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }
}
