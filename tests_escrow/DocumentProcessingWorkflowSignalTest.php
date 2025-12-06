<?php

use App\Models\KycTransaction;

test('kyc transaction has workflow_id and document_job_id columns', function () {
    // Verify the migration added the columns
    $columns = \DB::connection('central')
        ->getSchemaBuilder()
        ->getColumnListing('kyc_transactions');
    
    expect($columns)->toContain('workflow_id')
        ->and($columns)->toContain('document_job_id');
});

test('kyc transaction model allows mass assignment of workflow fields', function () {
    $transaction = KycTransaction::make([
        'transaction_id' => 'TEST-123',
        'tenant_id' => '01abc',
        'document_id' => '01def',
        'processor_execution_id' => '01ghi',
        'workflow_id' => '1',
        'document_job_id' => '01jkl',
        'status' => 'pending',
    ]);
    
    expect($transaction->workflow_id)->toBe('1')
        ->and($transaction->document_job_id)->toBe('01jkl');
});

test('workflow signal pattern stores workflow id in database', function () {
    // Create a tenant for the foreign key
    $tenant = \App\Models\Tenant::factory()->create();
    
    // Create a test transaction
    $transaction = KycTransaction::create([
        'transaction_id' => 'TEST-SIGNAL-123',
        'tenant_id' => $tenant->id,
        'document_id' => '01def',
        'processor_execution_id' => '01ghi',
        'workflow_id' => '42',
        'document_job_id' => '01jkl',
        'status' => 'pending',
    ]);
    
    // Retrieve from database
    $retrieved = KycTransaction::where('transaction_id', 'TEST-SIGNAL-123')->first();
    
    expect($retrieved->workflow_id)->toBe('42')
        ->and($retrieved->document_job_id)->toBe('01jkl')
        ->and($retrieved->tenant_id)->toBe($tenant->id);
});
