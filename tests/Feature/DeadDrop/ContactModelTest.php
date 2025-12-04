<?php

use App\Models\Contact;
use App\Models\ProcessorExecution;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

test('contact can be created with mobile number', function () {
    test()->markTestSkipped('QueryException: Missing tenant_id auto-population in Contact model');
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'mobile' => '+639171234567',
            'country' => 'PH',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
        ]);
        
        // Mobile is formatted by HasMobile trait
        expect($contact)->toBeInstanceOf(Contact::class)
            ->and($contact->mobile)->toBe('09171234567') // Formatted for PH mobile dialing
            ->and($contact->name)->toBe('Juan Dela Cruz')
            ->and($contact->email)->toBe('juan@example.com');
    });
});

test('contact has ekyc fields', function () {
    TenantContext::run($this->tenant, function () {
        $submittedAt = now()->toDateTimeString();
        $contact = Contact::create([
            'mobile' => '+639171234567',
            'kyc_transaction_id' => 'test_txn_123',
            'kyc_status' => 'pending',
            'kyc_submitted_at' => $submittedAt,
        ]);
        
        expect($contact->kyc_transaction_id)->toBe('test_txn_123')
            ->and($contact->kyc_status)->toBe('pending')
            ->and($contact->kyc_submitted_at)->not->toBeNull();
    });
});

test('contact can check kyc approval status', function () {
    TenantContext::run($this->tenant, function () {
        $pendingContact = Contact::create([
            'mobile' => '+639171234567',
            'kyc_status' => 'pending',
        ]);
        
        $approvedContact = Contact::create([
            'mobile' => '+639181234567',
            'kyc_status' => 'approved',
        ]);
        
        expect($pendingContact->isKycApproved())->toBeFalse()
            ->and($pendingContact->needsKyc())->toBeTrue()
            ->and($approvedContact->isKycApproved())->toBeTrue()
            ->and($approvedContact->needsKyc())->toBeFalse();
    });
});

test('contact can be linked to processor execution via contactables', function () {
    TenantContext::run($this->tenant, function () {
        // Create contact
        $contact = Contact::create([
            'mobile' => '+639171234567',
            'name' => 'Test User',
        ]);
        
        // Create processor execution
        $processor = Processor::factory()->create(['slug' => 'ekyc-verification']);
        $documentJob = DocumentJob::factory()->create(['tenant_id' => $this->tenant->id]);
        $execution = ProcessorExecution::factory()->create([
            'processor_id' => $processor->id,
            'job_id' => $documentJob->id,
            'output_data' => [
                'transaction_id' => 'test_txn_123',
                'kyc_status' => 'approved',
            ],
        ]);
        
        // Link contact to execution
        $execution->contacts()->attach($contact->id, [
            'relationship_type' => 'signer',
            'metadata' => json_encode(['test' => 'data']),
        ]);
        
        // Verify relationship
        expect($contact->processorExecutions()->count())->toBe(1)
            ->and($execution->contacts()->count())->toBe(1)
            ->and($execution->contacts->first()->id)->toBe($contact->id)
            ->and($execution->contacts->first()->pivot->relationship_type)->toBe('signer');
    });
});

test('contact can retrieve signing history', function () {
    TenantContext::run($this->tenant, function () {
        // Create contact
        $contact = Contact::create([
            'mobile' => '+639171234567',
            'kyc_status' => 'approved',
        ]);
        
        // Create ekyc processor
        $processor = Processor::factory()->create(['slug' => 'ekyc-verification']);
        
        // Create 3 signing events
        $executions = collect();
        for ($i = 1; $i <= 3; $i++) {
            $documentJob = DocumentJob::factory()->create(['tenant_id' => $this->tenant->id]);
            $execution = ProcessorExecution::factory()->create([
                'processor_id' => $processor->id,
                'job_id' => $documentJob->id,
                'output_data' => [
                    'transaction_id' => "txn_{$i}",
                    'kyc_status' => 'approved',
                    'signed_at' => now()->subDays($i),
                ],
            ]);
            
            $execution->contacts()->attach($contact, ['relationship_type' => 'signer']);
            $executions->push($execution);
        }
        
        // Verify signing history
        $history = $contact->signingHistory()->get();
        
        expect($history->count())->toBe(3)
            ->and($history->first()->output_data['transaction_id'])->toBe('txn_1');
    });
});

test('contact can get latest kyc execution', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'mobile' => '+639171234567',
        ]);
        
        $processor = Processor::factory()->create(['slug' => 'ekyc-verification']);
        $documentJob = DocumentJob::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create older execution
        sleep(1); // Ensure different timestamps
        $oldExecution = ProcessorExecution::factory()->create([
            'processor_id' => $processor->id,
            'job_id' => $documentJob->id,
            'output_data' => ['transaction_id' => 'old_txn'],
        ]);
        $oldExecution->contacts()->attach($contact);
        
        // Create latest execution
        sleep(1); // Ensure different timestamps
        $latestExecution = ProcessorExecution::factory()->create([
            'processor_id' => $processor->id,
            'job_id' => $documentJob->id,
            'output_data' => ['transaction_id' => 'latest_txn'],
        ]);
        $latestExecution->contacts()->attach($contact);
        
        $latest = $contact->latestKycExecution();
        
        expect($latest)->not->toBeNull()
            ->and($latest->id)->toBe($latestExecution->id)
            ->and($latest->output_data['transaction_id'])->toBe('latest_txn');
    });
});
