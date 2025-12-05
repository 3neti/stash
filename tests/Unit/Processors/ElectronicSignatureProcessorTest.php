<?php

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Contact;
use App\Models\Document;
use App\Processors\ElectronicSignatureProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\HyperVerge\Actions\Document\MarkDocumentWithKYC;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock the MarkDocumentWithKYC action
    $this->markDocumentAction = Mockery::mock(MarkDocumentWithKYC::class);
    app()->instance(MarkDocumentWithKYC::class, $this->markDocumentAction);
});

test('processor requires transaction_id in config', function () {
    $processor = new ElectronicSignatureProcessor();
    
    $document = Document::factory()->create([
        'mime_type' => 'application/pdf',
    ]);
    
    $config = new ProcessorConfigData(
        config: [] // Missing transaction_id
    );
    
    $context = new ProcessorContextData();
    
    expect(fn() => $processor->handle($document, $config, $context))
        ->toThrow(RuntimeException::class, 'transaction_id is required');
});

test('processor requires approved KYC for transaction', function () {
    $processor = new ElectronicSignatureProcessor();
    
    $document = Document::factory()->create([
        'mime_type' => 'application/pdf',
    ]);
    
    $config = new ProcessorConfigData(
        config: [
            'transaction_id' => 'EKYC-TEST-12345',
        ]
    );
    
    $context = new ProcessorContextData();
    
    expect(fn() => $processor->handle($document, $config, $context))
        ->toThrow(RuntimeException::class, 'KYC verification not approved');
});

test('processor signs document with approved KYC', function () {
    $processor = new ElectronicSignatureProcessor();
    
    // Create approved contact
    $contact = Contact::factory()->create([
        'kyc_transaction_id' => 'EKYC-TEST-12345',
        'kyc_status' => 'approved',
        'kyc_completed_at' => now(),
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'mobile' => '+639171234567',
    ]);
    
    // Create PDF document
    $document = Document::factory()->create([
        'mime_type' => 'application/pdf',
        'file_name' => 'test.pdf',
    ]);
    
    // Mock file system
    Storage::fake('tenant');
    $pdfPath = storage_path('app/test.pdf');
    file_put_contents($pdfPath, 'PDF content');
    
    // Mock MarkDocumentWithKYC result
    $mockSignedDoc = Mockery::mock(Media::class);
    $mockSignedDoc->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $mockSignedDoc->shouldReceive('getAttribute')->with('file_name')->andReturn('test_signed.pdf');
    $mockSignedDoc->shouldReceive('getAttribute')->with('size')->andReturn(12345);
    $mockSignedDoc->shouldReceive('getAttribute')->with('mime_type')->andReturn('application/pdf');
    $mockSignedDoc->shouldReceive('getUrl')->andReturn('https://storage.test/signed.pdf');
    
    $mockStamp = Mockery::mock(Media::class);
    $mockStamp->shouldReceive('getAttribute')->with('id')->andReturn(2);
    $mockStamp->shouldReceive('getAttribute')->with('file_name')->andReturn('stamp.png');
    $mockStamp->shouldReceive('getUrl')->andReturn('https://storage.test/stamp.png');
    
    MarkDocumentWithKYC::shouldReceive('run')
        ->once()
        ->with(
            Mockery::on(fn($doc) => $doc->id === $document->id),
            'EKYC-TEST-12345',
            Mockery::on(fn($metadata) => 
                $metadata['name'] === 'John Doe' &&
                $metadata['email'] === 'john@example.com'
            ),
            1, // tile
            null // logoPath
        )
        ->andReturn([
            'signed_document' => $mockSignedDoc,
            'stamp' => $mockStamp,
        ]);
    
    $config = new ProcessorConfigData(
        config: [
            'transaction_id' => 'EKYC-TEST-12345',
            'tile' => 1,
        ]
    );
    
    $context = new ProcessorContextData();
    
    $result = $processor->handle($document, $config, $context);
    
    expect($result->success)->toBeTrue()
        ->and($result->output)->toHaveKeys([
            'signed_document',
            'stamp',
            'transaction_id',
            'verification_url',
            'signer_info',
            'signature_timestamp',
        ])
        ->and($result->output['transaction_id'])->toBe('EKYC-TEST-12345')
        ->and($result->output['signer_info']['contact_id'])->toBe($contact->id)
        ->and($result->output['signer_info']['name'])->toBe('John Doe')
        ->and($result->output['signed_document']['media_id'])->toBe(1);
    
    // Cleanup
    @unlink($pdfPath);
});

test('processor only accepts PDF documents', function () {
    $processor = new ElectronicSignatureProcessor();
    
    $pdfDocument = Document::factory()->create(['mime_type' => 'application/pdf']);
    $imageDocument = Document::factory()->create(['mime_type' => 'image/jpeg']);
    
    expect($processor->canProcess($pdfDocument))->toBeTrue()
        ->and($processor->canProcess($imageDocument))->toBeFalse();
});

test('processor has correct category and name', function () {
    $processor = new ElectronicSignatureProcessor();
    
    expect($processor->getCategory())->toBe('signing')
        ->and($processor->getName())->toBe('Electronic Signature');
});

test('processor has valid output schema', function () {
    $processor = new ElectronicSignatureProcessor();
    $schema = $processor->getOutputSchema();
    
    expect($schema)->toBeArray()
        ->and($schema['type'])->toBe('object')
        ->and($schema['required'])->toContain('signed_document', 'stamp', 'transaction_id')
        ->and($schema['properties'])->toHaveKeys([
            'signed_document',
            'stamp',
            'transaction_id',
            'verification_url',
            'signer_info',
            'signature_timestamp',
            'tile_position',
            'metadata',
        ]);
});
