<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\UploadDocument;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ProcessDocumentCommand extends Command
{
    protected $signature = 'document:process 
                            {documents* : Paths to document files}
                            {--user= : User email (defaults to admin user)}
                            {--tenant= : Tenant slug}
                            {--campaign= : Campaign slug}
                            {--wait : Wait and show real-time progress}
                            {--show-output : Display full processor outputs}
                            {--json : Output results as JSON}
                            {--dry-run : Validate without processing}';

    protected $description = 'Upload and process one or more documents through the pipeline';

    /**
     * Check if JSON output mode is enabled.
     */
    private function shouldOutputJson(): bool
    {
        return (bool) $this->option('json');
    }

    /**
     * Output a message conditionally (suppressed in JSON mode).
     */
    private function conditionalOutput(string $message, string $type = 'info'): void
    {
        if ($this->shouldOutputJson()) {
            return;
        }

        match ($type) {
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            'line' => $this->line($message),
            default => $this->info($message),
        };
    }

    /**
     * Output JSON result.
     */
    private function outputJson(array $data): void
    {
        $this->getOutput()->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function handle(): int
    {
        $documentPaths = $this->argument('documents');

        // Validate all files exist before processing
        $missingFiles = [];
        foreach ($documentPaths as $path) {
            if (! file_exists($path)) {
                $missingFiles[] = $path;
            }
        }

        if (! empty($missingFiles)) {
            $this->error('The following files were not found:');
            foreach ($missingFiles as $file) {
                $this->line("  - {$file}");
            }

            return 2; // Validation error
        }

        // 1. Determine user context
        $userEmail = $this->option('user');
        $user = null;
        $tenant = null;

        if ($userEmail) {
            $user = User::on('central')->where('email', $userEmail)->first();
            if (! $user) {
                $this->error("User not found: {$userEmail}");

                return self::FAILURE;
            }

            // Get tenant from user
            $tenant = $user->tenants()->first();
            if (! $tenant) {
                $this->error("User {$userEmail} is not associated with any tenant");

                return self::FAILURE;
            }

            $this->conditionalOutput("✓ Processing as user: {$user->name} ({$user->email})");
        }

        // 2. Find or use specified tenant (if not from user)
        if (! $tenant) {
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
        }

        $this->conditionalOutput("✓ Using tenant: {$tenant->name} ({$tenant->slug})");
        if (! $this->shouldOutputJson()) {
            $this->newLine();
        }

        // 3. Process each document
        $results = [];
        $totalCount = count($documentPaths);

        foreach ($documentPaths as $index => $filePath) {
            $documentNumber = $index + 1;

            if ($totalCount > 1 && ! $this->shouldOutputJson()) {
                $this->info("Processing document {$documentNumber} of {$totalCount}:");
            }

            $result = $this->processDocument($filePath, $tenant);
            $results[] = $result;

            if ($totalCount > 1) {
                $this->newLine();
            }
        }

        // 4. Output results
        if ($this->shouldOutputJson()) {
            // JSON output mode
            $successCount = count(array_filter($results, fn ($r) => $r['success']));
            $this->outputJson([
                'success' => $successCount === $totalCount,
                'processed' => $totalCount,
                'successful' => $successCount,
                'failed' => $totalCount - $successCount,
                'documents' => array_map(function ($result) {
                    return [
                        'file' => $result['filename'],
                        'document_id' => $result['document_uuid'],
                        'job_id' => $result['job_uuid'],
                        'campaign' => $result['campaign'],
                        'status' => $result['status'],
                        'duration_ms' => (int) ($result['duration'] * 1000),
                        'outputs' => $result['outputs'] ?? null,
                        'error' => $result['error'],
                    ];
                }, $results),
            ]);
        } else {
            // Standard output mode
            if ($totalCount > 1) {
                $this->displayBatchSummary($results);
            }
        }

        // 5. Determine exit code
        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $failureCount = $totalCount - $successCount;

        if ($failureCount === 0) {
            return self::SUCCESS;
        } elseif ($successCount === 0) {
            return 2; // All failed
        } else {
            return 1; // Some failed
        }
    }

    /**
     * Process a single document.
     */
    private function processDocument(string $filePath, Tenant $tenant): array
    {
        $startTime = microtime(true);
        $document = null;
        $documentJob = null;
        $campaign = null;
        $success = false;
        $error = null;

        try {
            TenantContext::run($tenant, function () use ($filePath, &$document, &$documentJob, &$campaign, &$error) {
                // Find campaign
                $campaignSlug = $this->option('campaign');
                if ($campaignSlug) {
                    $campaign = Campaign::where('slug', $campaignSlug)->first();
                    if (! $campaign) {
                        $error = "Campaign not found: {$campaignSlug}";
                        $this->error($error);

                        return;
                    }
                } else {
                    $campaign = Campaign::whereNotNull('published_at')->first();
                    if (! $campaign) {
                        $error = 'No published campaigns found';
                        $this->error($error);

                        return;
                    }
                }

                $this->conditionalOutput("✓ Using campaign: {$campaign->name} ({$campaign->slug})");

                // Create UploadedFile instance from file path
                $uploadedFile = new UploadedFile(
                    $filePath,
                    basename($filePath),
                    mime_content_type($filePath),
                    null,
                    true // test mode
                );

                // Upload document using UploadDocument action
                $this->conditionalOutput("Uploading {$uploadedFile->getClientOriginalName()}...");
                $uploadAction = app(UploadDocument::class);
                $document = $uploadAction->handle($campaign, $uploadedFile);

                $this->conditionalOutput("✓ Document uploaded: {$document->uuid}");
                $this->conditionalOutput("  - Size: {$document->formatted_size}");

                // Get document job
                $documentJob = $document->documentJob()->first();
                if ($documentJob) {
                    $this->conditionalOutput("✓ Job created: {$documentJob->uuid}");

                    // Wait for processing if requested
                    if ($this->option('wait')) {
                        $this->waitForProcessing($documentJob);
                    }
                } else {
                    $this->conditionalOutput('No job was created for this document', 'warn');
                }
            });

            if ($document && ! $error) {
                $success = true;
                
                // Show single document summary if not in batch mode and not JSON mode
                if (! $this->shouldOutputJson() && count($this->argument('documents')) === 1 && ! $this->option('wait')) {
                    $this->newLine();
                    $this->info('✅ Document processing initiated successfully!');
                    $this->newLine();

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
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->error("Failed to process document: {$error}");
        }

        $duration = microtime(true) - $startTime;

        // Collect processor outputs if JSON mode and outputs requested
        $outputs = null;
        if ($this->shouldOutputJson() && $this->option('show-output') && $documentJob) {
            $documentJob->load('processorExecutions.processor');
            $outputs = [];
            foreach ($documentJob->processorExecutions as $execution) {
                if ($execution->isCompleted() && $execution->output_data) {
                    $processorSlug = $execution->processor->slug ?? 'unknown';
                    $outputs[$processorSlug] = $execution->output_data;
                }
            }
        }

        return [
            'filename' => basename($filePath),
            'document_uuid' => $document?->uuid,
            'job_uuid' => $documentJob?->uuid,
            'campaign' => $campaign?->name,
            'status' => (string) ($documentJob?->state ?? ($success ? 'uploaded' : 'failed')),
            'duration' => $duration,
            'success' => $success,
            'error' => $error,
            'outputs' => $outputs,
        ];
    }

    /**
     * Display batch processing summary.
     */
    private function displayBatchSummary(array $results): void
    {
        $this->newLine();
        $this->info('Batch Processing Summary:');
        $this->newLine();

        $rows = [];
        foreach ($results as $result) {
            $statusIcon = $result['success'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $status = $statusIcon . ' ' . ucfirst($result['status']);
            $duration = number_format($result['duration'], 2) . 's';
            $jobId = $result['job_uuid'] ? substr($result['job_uuid'], 0, 8) . '...' : 'N/A';
            $docId = $result['document_uuid'] ? substr($result['document_uuid'], 0, 8) . '...' : 'N/A';

            $rows[] = [
                $result['filename'],
                $status,
                $duration,
                $jobId,
                $docId,
            ];
        }

        $this->table(
            ['File', 'Status', 'Duration', 'Job ID', 'Doc ID'],
            $rows
        );

        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $totalCount = count($results);

        $this->newLine();
        if ($successCount === $totalCount) {
            $this->info("✅ All {$totalCount} document(s) processed successfully!");
        } else {
            $failCount = $totalCount - $successCount;
            $this->warn("⚠ {$successCount} succeeded, {$failCount} failed");
        }
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
                        
                        // Show JSON output for this stage if --show-output flag is set
                        if ($this->option('show-output') && $execution->output_data) {
                            $this->newLine();
                            $this->line("    <fg=yellow>Output:</>");
                            $json = json_encode($execution->output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            foreach (explode("\n", $json) as $line) {
                                $this->line("    <fg=gray>{$line}</>");
                            }
                            $this->newLine();
                        }
                    } elseif ($execution->isFailed()) {
                        $this->line("  <fg=red>✗</> {$processorName} <fg=gray>({$duration})</>");
                        
                        // Show error message
                        if ($execution->error_message) {
                            $this->line("    <fg=red>Error: {$execution->error_message}</>");
                        }
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

            // Display output data for each processor (only if --show-output flag is set)
            if ($this->option('show-output')) {
                foreach ($documentJob->processorExecutions as $execution) {
                    if ($execution->isCompleted() && $execution->output_data) {
                        $this->newLine();
                        $processorName = $execution->processor?->name ?? 'Unknown';
                        $this->line("<fg=cyan>Results from {$processorName}:</>");
                        $this->displayOutputData($execution->output_data);
                    }
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
