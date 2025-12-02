<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\UploadDocument;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ProcessDocumentCommand extends Command
{
    protected $signature = 'document:process 
                            {file : Path to the document file}
                            {--tenant= : Tenant slug (defaults to first active tenant)}
                            {--campaign= : Campaign slug (defaults to first active campaign)}
                            {--wait : Wait and show processing status}';

    protected $description = 'Upload and process a document through the pipeline';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Validate file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Processing document: {$filePath}");
        $this->newLine();

        // 1. Find or use specified tenant
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

        $this->info("✓ Using tenant: {$tenant->name} ({$tenant->slug})");

        // 2. Initialize tenant context and find campaign
        $document = null;
        $documentJob = null;

        TenantContext::run($tenant, function () use ($filePath, &$document, &$documentJob) {
            // Find campaign
            $campaignSlug = $this->option('campaign');
            if ($campaignSlug) {
                $campaign = Campaign::where('slug', $campaignSlug)->first();
                if (! $campaign) {
                    $this->error("Campaign not found: {$campaignSlug}");

                    return;
                }
            } else {
                $campaign = Campaign::whereNotNull('published_at')->first();
                if (! $campaign) {
                    $this->error('No published campaigns found');

                    return;
                }
            }

            $this->info("✓ Using campaign: {$campaign->name} ({$campaign->slug})");
            $this->newLine();

            // 3. Create UploadedFile instance from file path
            $uploadedFile = new UploadedFile(
                $filePath,
                basename($filePath),
                mime_content_type($filePath),
                null,
                true // test mode
            );

            // 4. Upload document using UploadDocument action
            $this->info('Uploading document...');
            $uploadAction = app(UploadDocument::class);
            $document = $uploadAction->handle($campaign, $uploadedFile);

            $this->info("✓ Document uploaded: {$document->uuid}");
            $this->info("  - Filename: {$document->original_filename}");
            $this->info("  - Size: {$document->formatted_size}");
            $this->info("  - Hash: {$document->hash}");
            $this->newLine();

            // 5. Get document job
            $documentJob = $document->documentJob()->first();
            if ($documentJob) {
                $this->info("✓ Document job created: {$documentJob->uuid}");
                $this->info("  - Status: {$documentJob->state}");
                $this->newLine();
            }

            // 6. Wait for processing if requested
            if ($this->option('wait') && $documentJob) {
                $this->info('Waiting for processing to complete...');
                $this->waitForProcessing($documentJob);
            }
        });

        if (! $document) {
            return self::FAILURE;
        }

        $this->info('✅ Document processing initiated successfully!');
        $this->newLine();

        // Display document info
        $this->table(
            ['Property', 'Value'],
            [
                ['Document UUID', $document->uuid],
                ['Campaign', $campaign->name ?? 'N/A'],
                ['Filename', $document->original_filename],
                ['Status', $document->state ?? 'N/A'],
                ['Storage Path', $document->storage_path],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Wait for document processing to complete and show status updates.
     */
    private function waitForProcessing($documentJob): void
    {
        $maxWaitTime = 300; // 5 minutes
        $startTime = time();
        $lastActivityCount = 0;
        $shownActivities = [];

        $this->info('Processing...');
        $this->newLine();

        while (time() - $startTime < $maxWaitTime) {
            $documentJob->refresh();
            $documentJob->load(['document', 'processorExecutions.processor']);

            // Show new processor executions as they complete
            $executions = $documentJob->processorExecutions;
            if ($executions->count() > $lastActivityCount) {
                $newExecutions = $executions->slice($lastActivityCount);
                foreach ($newExecutions as $execution) {
                    $processorName = $execution->processor->name ?? 'Unknown';
                    $status = $execution->state ?? 'running';
                    $duration = $execution->duration_ms ? "{$execution->duration_ms}ms" : 'running...';
                    
                    if ($execution->isCompleted()) {
                        $this->line("  <fg=green>✓</> {$processorName} <fg=gray>({$duration})</>");
                    } elseif ($execution->isFailed()) {
                        $this->line("  <fg=red>✗</> {$processorName} <fg=gray>({$duration})</>");
                    }
                }
                $lastActivityCount = $executions->count();
            }

            // Check completion
            if ($documentJob->isCompleted()) {
                $this->newLine();
                $this->info('🎉 Processing completed!');
                $this->displayProcessingResults($documentJob);
                return;
            }

            if ($documentJob->isFailed()) {
                $this->newLine();
                $this->error('✗ Processing failed!');
                $this->displayProcessingResults($documentJob);
                return;
            }

            sleep(1); // Poll every second for faster updates
        }

        $this->newLine();
        $this->warn('⚠ Processing timeout reached. Job may still be running.');
        if ($lastActivityCount > 0) {
            $this->displayProcessingResults($documentJob);
        }
    }

    /**
     * Display processing results.
     */
    private function displayProcessingResults($documentJob): void
    {
        $this->newLine();
        $this->info('Processing Results:');

        // Load related data
        $documentJob->load(['document', 'processorExecutions']);

        // Display document status
        $this->table(
            ['Property', 'Value'],
            [
                ['Job UUID', $documentJob->uuid],
                ['Status', $documentJob->state],
                ['Attempts', $documentJob->attempts],
                ['Started At', $documentJob->started_at?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Completed At', $documentJob->completed_at?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Failed At', $documentJob->failed_at?->format('Y-m-d H:i:s') ?? 'N/A'],
            ]
        );

        // Display processor executions
        if ($documentJob->processorExecutions->isNotEmpty()) {
            $this->newLine();
            $this->info('Processor Executions:');

            $rows = [];
            foreach ($documentJob->processorExecutions as $execution) {
                $processorName = $execution->processor?->name ?? 'Unknown';
                $status = $execution->state ?? 'N/A';
                $duration = $execution->duration_ms ? "{$execution->duration_ms}ms" : 'N/A';
                $completed = $execution->completed_at?->format('H:i:s') ?? 'N/A';

                $rows[] = [
                    $processorName,
                    $status,
                    $duration,
                    $completed,
                ];
            }

            $this->table(
                ['Processor', 'Status', 'Duration', 'Completed'],
                $rows
            );

            // Display output data for each processor
            foreach ($documentJob->processorExecutions as $execution) {
                if ($execution->isCompleted() && $execution->output_data) {
                    $this->newLine();
                    $processorName = $execution->processor?->name ?? 'Unknown';
                    $this->line("<fg=cyan>Results from {$processorName}:</>");
                    $this->displayOutputData($execution->output_data);
                }
            }
        }

        // Display error log if failed
        if ($documentJob->isFailed() && $documentJob->error_log) {
            $this->newLine();
            $this->error('Error Log:');
            foreach ($documentJob->error_log as $error) {
                $this->line("  [{$error['timestamp']}] Attempt {$error['attempt']}: {$error['error']}");
            }
        }
    }

    /**
     * Display output data in a readable format.
     */
    private function displayOutputData(array $data, int $indent = 0): void
    {
        $indentStr = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line("{$indentStr}<comment>{$key}:</comment>");
                $this->displayOutputData($value, $indent + 1);
            } else {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $this->line("{$indentStr}<comment>{$key}:</comment> {$displayValue}");
            }
        }
    }
}
