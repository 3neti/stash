<?php

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Tenant;
use App\Processors\EKycVerificationProcessor;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;


beforeEach(function () {
    test()->markTestSkipped('Pending: EKycVerificationProcessor class does not exist yet');
    
    // Set up HyperVerge test credentials
    config([
        'hyperverge.test_mode' => true,
        'hyperverge.app_id' => 'test_app_id',
        'hyperverge.app_key' => 'test_app_key',
        'hyperverge.base_url' => 'https://test.hyperverge.co',
    ]);
    
    $this->tenant = Tenant::factory()->create();
    $this->processor = new EKycVerificationProcessor();
});

test('processor generates kyc link for new contact', function () {
    TenantContext::run($this->tenant, function () {
        
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);
        
        $config = new ProcessorConfigData(
            id: 'ekyc-verification',
            type: 'verification',
            config: [
                'contact' => [
                    'mobile' => '+639171234567',
                    'email' => 'test@example.com',
                    'name' => 'Juan Dela Cruz',
                ],
                'workflow_id' => 'workflow_2nQDNT',
            ]
        );
        
        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );
        
        $result = $this->processor->handle($document, $config, $context);
        
        expect($result->success)->toBeTrue()
            ->and($result->output)->toHaveKey('transaction_id')
            ->and($result->output)->toHaveKey('kyc_link')
            ->and($result->output['kyc_status'])->toBe('pending')
            ->and($result->output['awaiting_webhook'])->toBeTrue();
        
        // Verify contact was created
        $contact = Contact::where('mobile', '09171234567')->first(); // Formatted by HasMobile trait
        expect($contact)->not->toBeNull()
            ->and($contact->kyc_status)->toBe('pending')
            ->and($contact->kyc_transaction_id)->not->toBeNull();
    });
});

test('processor skips kyc for already approved contact', function () {
    TenantContext::run($this->tenant, function () {
        // Create contact with approved KYC
        $contact = Contact::create([
            'mobile' => '+639171234567',
            'kyc_status' => 'approved',
            'kyc_transaction_id' => 'existing_txn_123',
        ]);
        
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);
        
        $config = new ProcessorConfigData(
            id: 'ekyc-verification',
            type: 'verification',
            config: [
                'contact' => [
                    'mobile' => '+639171234567',
                ],
            ]
        );
        
        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );
        
        $result = $this->processor->handle($document, $config, $context);
        
        expect($result->success)->toBeTrue()
            ->and($result->output['kyc_status'])->toBe('already_approved')
            ->and($result->output['contact_id'])->toBe($contact->id)
            ->and($result->output['transaction_id'])->toBe('existing_txn_123')
            ->and($result->output['skip_verification'])->toBeTrue();
        
        // Verify no HTTP call was made to HyperVerge
        Http::assertNothingSent();
    });
});

test('processor generates unique transaction ids', function () {
    TenantContext::run($this->tenant, function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);
        
        // Generate multiple transaction IDs with different mobile numbers
        $transactionIds = [];
        for ($i = 0; $i < 3; $i++) {
            $config = new ProcessorConfigData(
                id: 'ekyc-verification',
                type: 'verification',
                config: [
                    'contact' => ['mobile' => "+63917123456{$i}"], // Unique mobile for each
                ]
            );
            
            $context = new ProcessorContextData(
                documentJobId: 'test-job-123',
                processorIndex: 0
            );
            
            $result = $this->processor->handle($document, $config, $context);
            $transactionIds[] = $result->output['transaction_id'];
            sleep(1); // Ensure different timestamps
        }
        
        // Verify all transaction IDs are unique
        expect(count($transactionIds))->toBe(count(array_unique($transactionIds)));
        
        // Verify transaction ID format
        foreach ($transactionIds as $txnId) {
            expect($txnId)->toStartWith('ekyc_')
                ->and($txnId)->toContain($document->campaign_id)
                ->and($txnId)->toContain($document->id);
        }
    });
});

test('processor uses custom workflow id from config', function () {
    TenantContext::run($this->tenant, function () {
        
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);
        
        $customWorkflowId = 'workflow_custom_123';
        
        $config = new ProcessorConfigData(
            id: 'ekyc-verification',
            type: 'verification',
            config: [
                'contact' => ['mobile' => '+639171234567'],
                'workflow_id' => $customWorkflowId,
            ]
        );
        
        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );
        
        $result = $this->processor->handle($document, $config, $context);
        
        expect($result->output['workflow_id'])->toBe($customWorkflowId);
    });
});

test('processor returns correct output schema', function () {
    $schema = $this->processor->getOutputSchema();
    
    expect($schema)->toBeArray()
        ->and($schema)->toHaveKey('type')
        ->and($schema)->toHaveKey('properties')
        ->and($schema)->toHaveKey('required')
        ->and($schema['properties'])->toHaveKey('transaction_id')
        ->and($schema['properties'])->toHaveKey('kyc_link')
        ->and($schema['properties'])->toHaveKey('kyc_status')
        ->and($schema['required'])->toContain('transaction_id')
        ->and($schema['required'])->toContain('kyc_link');
});
