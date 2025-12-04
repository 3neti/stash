<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\KycTransaction;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use Illuminate\Console\Command;
use LBHurtado\HyperVerge\Actions\Results\ExtractKYCImages;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;

class TestKycFetch extends Command
{
    protected $signature = 'kyc:test-fetch 
                            {transactionId : HyperVerge transaction ID}
                            {--status=auto_approved : Status from callback}
                            {--document= : Document UUID (optional)}';

    protected $description = 'Test fetching KYC data from HyperVerge';

    public function handle(): int
    {
        $transactionId = $this->argument('transactionId');
        $status = $this->option('status');
        $documentUuid = $this->option('document');

        $this->info("Testing KYC fetch for transaction: {$transactionId}");
        $this->line('');

        // Find transaction in registry
        $kycTransaction = KycTransaction::where('transaction_id', $transactionId)->first();

        if (!$kycTransaction) {
            $this->error('Transaction not found in registry');
            return self::FAILURE;
        }

        $this->info('✓ Found transaction in registry');
        $this->line("  Tenant: {$kycTransaction->tenant_id}");
        $this->line("  Document: {$kycTransaction->document_id}");
        $this->line('');

        // Initialize tenant
        $tenant = Tenant::on('central')->find($kycTransaction->tenant_id);
        app(TenancyService::class)->initializeTenant($tenant);
        $this->info('✓ Tenant initialized');

        // Load document
        $document = Document::find($kycTransaction->document_id);
        if ($document) {
            $this->info("✓ Document loaded: {$document->uuid}");
        } else {
            $this->warn('⚠ Document not found');
        }
        $this->line('');

        try {
            // Test 1: Fetch result
            $this->info('Step 1: Fetching KYC result from HyperVerge...');
            $result = FetchKYCResult::make()->handle($transactionId, $document);
            
            $this->info('✓ Result fetched successfully');
            $this->line("  Type: " . get_class($result));
            $this->line("  Application Status: {$result->applicationStatus}");
            $this->line("  Transaction ID: {$result->transactionId}");
            $this->line("  Modules: " . count($result->modules));
            $this->line('');

            // Test 2: Validate result
            $this->info('Step 2: Validating KYC result...');
            $validation = ValidateKYCResult::make()->handle($result);
            
            $this->info('✓ Validation completed');
            $this->line("  Type: " . get_class($validation));
            $this->line("  Valid: " . ($validation->valid ? 'YES' : 'NO'));
            $this->line("  Status: {$validation->status}");
            if (!empty($validation->reasons)) {
                $this->line("  Reasons: " . implode(', ', $validation->reasons));
            }
            $this->line('');

            // Test 3: Extract images
            $this->info('Step 3: Extracting image URLs...');
            $imageUrls = ExtractKYCImages::run($transactionId, $document);
            
            $this->info('✓ Images extracted');
            $this->line("  Count: " . count($imageUrls));
            foreach ($imageUrls as $key => $url) {
                $this->line("  - {$key}: {$url}");
            }
            $this->line('');

            $this->info('🎉 All tests passed!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('');
            $this->line('Stack trace:');
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
