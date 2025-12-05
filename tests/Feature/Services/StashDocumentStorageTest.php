<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Models\Tenant;
use App\Services\StashDocumentStorage;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\DeadDropTestCase;

uses(DeadDropTestCase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->storage = new StashDocumentStorage();
    
    // Setup storage fakes
    Storage::fake('local');
    Storage::fake('public');
});

// Helper to create test data within tenant context
function createTestData()
{
    $campaign = Campaign::create([
        'name' => 'Test E-Signature Campaign',
        'slug' => 'test-e-signature',
        'settings' => ['locale' => 'en'],
        'pipeline_config' => [],
    ]);
    
    $processor = Processor::create([
        'name' => 'Electronic Signature',
        'slug' => 'electronic-signature',
        'category' => 'signing',
        'class_name' => 'App\\Processors\\ElectronicSignatureProcessor',
        'config' => [],
    ]);
    
    $testFile = UploadedFile::fake()->create('test-document.pdf', 100, 'application/pdf');
    $filePath = $testFile->store('documents', 'public');
    
    $document = Document::create([
        'campaign_id' => $campaign->id,
        'filename' => 'test-document.pdf',
        'original_filename' => 'test-document.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'storage_disk' => 'public',
        'storage_path' => $filePath,
        'hash' => 'test-hash',
        'checksum' => 'test-checksum',
    ]);
    
    $documentJob = DocumentJob::create([
        'document_id' => $document->id,
        'campaign_id' => $campaign->id,
        'status' => 'pending',
        'pipeline_instance' => [],
    ]);
    
    $execution = ProcessorExecution::create([
        'job_id' => $documentJob->id,
        'processor_id' => $processor->id,
        'status' => 'pending',
        'config' => [],
        'input_data' => [],
        'output_data' => [],
    ]);
    
    return compact('campaign', 'processor', 'document', 'documentJob', 'execution');
}

// Interface compliance tests
test('implements DocumentStoragePort interface', function () {
    expect($this->storage)->toBeInstanceOf(DocumentStoragePort::class);
});

test('has all required interface methods', function () {
    expect(method_exists($this->storage, 'storeDocument'))->toBeTrue()
        ->and(method_exists($this->storage, 'getDocument'))->toBeTrue()
        ->and(method_exists($this->storage, 'getPath'))->toBeTrue()
        ->and(method_exists($this->storage, 'getUrl'))->toBeTrue()
        ->and(method_exists($this->storage, 'hasDocument'))->toBeTrue()
        ->and(method_exists($this->storage, 'deleteDocument'))->toBeTrue();
});

// getDocument() tests - Document model
test('returns object with getPath() for existing document', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        
        $media = $this->storage->getDocument($data['document'], 'documents');
        
        expect($media)->not->toBeNull()
            ->and(method_exists($media, 'getPath'))->toBeTrue()
            ->and(method_exists($media, 'getUrl'))->toBeTrue();
    });
});

test('getPath() returns absolute file path', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $document = $data['document'];
        
        $media = $this->storage->getDocument($document, 'documents');
        $path = $media->getPath();
        
        expect($path)
            ->toBeString()
            ->toContain($document->storage_path)
            ->and(Storage::disk('public')->exists($document->storage_path))
            ->toBeTrue();
    });
});

test('returns null for non-existent document', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $document = $data['document'];
        
        Storage::disk('public')->delete($document->storage_path);
        
        $media = $this->storage->getDocument($document, 'documents');
        
        expect($media)->toBeNull();
    });
});

// getDocument() tests - ProcessorExecution model
test('returns Media object for existing artifact', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $execution = $data['execution'];
        
        $signedFile = UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf');
        $signedPath = $signedFile->store('temp', 'local');
        
        $execution->addMedia(Storage::disk('local')->path($signedPath))
            ->toMediaCollection('signed_documents');
        
        $media = $this->storage->getDocument($execution, 'signed_documents');
        
        expect($media)
            ->toBeInstanceOf(Media::class)
            ->and($media->collection_name)->toBe('signed_documents');
    });
});

// storeDocument() tests - ProcessorExecution direct
test('stores document in ProcessorExecution media collection', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $execution = $data['execution'];
        
        $file = UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf');
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        $media = $this->storage->storeDocument(
            $execution,
            $absolutePath,
            'signed_documents',
            ['source' => 'test']
        );
        
        expect($media)
            ->toBeInstanceOf(Media::class)
            ->and($media->collection_name)->toBe('signed_documents')
            ->and($media->getCustomProperty('source'))->toBe('test')
            ->and($execution->hasMedia('signed_documents'))->toBeTrue();
    });
});

test('stores custom properties with document', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $execution = $data['execution'];
        
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        $customProps = [
            'transaction_id' => 'EKYC-123',
            'signed_at' => now()->toIso8601String(),
            'signer' => 'John Doe',
        ];
        
        $media = $this->storage->storeDocument(
            $execution,
            $absolutePath,
            'signed_documents',
            $customProps
        );
        
        expect($media->getCustomProperty('transaction_id'))->toBe('EKYC-123')
            ->and($media->getCustomProperty('signer'))->toBe('John Doe');
    });
});

// storeDocument() tests - Document model (finds ProcessorExecution)
test('stores in ProcessorExecution when given Document model', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        
        $file = UploadedFile::fake()->create('signed.pdf', 200);
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        $media = $this->storage->storeDocument(
            $data['document'],
            $absolutePath,
            'signed_documents'
        );
        
        expect($media)
            ->toBeInstanceOf(Media::class)
            ->and($data['execution']->fresh()->hasMedia('signed_documents'))->toBeTrue();
    });
});

test('throws exception when no ProcessorExecution exists', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        
        // Create document without job/execution
        $orphanDoc = Document::create([
            'campaign_id' => $data['campaign']->id,
            'filename' => 'orphan.pdf',
            'original_filename' => 'orphan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_disk' => 'public',
            'storage_path' => 'documents/orphan.pdf',
            'hash' => 'orphan-hash',
            'checksum' => 'orphan-checksum',
        ]);
        
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        expect(fn() => $this->storage->storeDocument(
            $orphanDoc,
            $absolutePath,
            'signed_documents'
        ))->toThrow(
            RuntimeException::class,
            'Cannot store signed document: No ProcessorExecution found'
        );
    });
});

// hasDocument() tests
test('returns true for existing Document file', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        
        $exists = $this->storage->hasDocument($data['document'], 'documents');
        
        expect($exists)->toBeTrue();
    });
});

test('returns false for deleted Document file', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $document = $data['document'];
        
        Storage::disk('public')->delete($document->storage_path);
        
        $exists = $this->storage->hasDocument($document, 'documents');
        
        expect($exists)->toBeFalse();
    });
});

test('returns true for existing ProcessorExecution media', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $execution = $data['execution'];
        
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        $execution->addMedia($absolutePath)
            ->toMediaCollection('signed_documents');
        
        $exists = $this->storage->hasDocument($execution, 'signed_documents');
        
        expect($exists)->toBeTrue();
    });
});

// deleteDocument() tests
test('deletes Document file from storage', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $document = $data['document'];
        $storagePath = $document->storage_path;
        
        expect(Storage::disk('public')->exists($storagePath))->toBeTrue();
        
        $deleted = $this->storage->deleteDocument($document, 'documents');
        
        expect($deleted)->toBeTrue()
            ->and(Storage::disk('public')->exists($storagePath))->toBeFalse();
    });
});

test('deletes ProcessorExecution media', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $execution = $data['execution'];
        
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $tempPath = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($tempPath);
        
        $execution->addMedia($absolutePath)
            ->toMediaCollection('signed_documents');
        
        expect($execution->hasMedia('signed_documents'))->toBeTrue();
        
        $deleted = $this->storage->deleteDocument($execution, 'signed_documents');
        
        expect($deleted)->toBeTrue()
            ->and($execution->fresh()->hasMedia('signed_documents'))->toBeFalse();
    });
});

// Integration test
test('simulates complete e-signature document lifecycle', function () {
    TenantContext::run($this->tenant, function () {
        $data = createTestData();
        $document = $data['document'];
        $execution = $data['execution'];
        
        // 1. Check original document exists
        expect($this->storage->hasDocument($document, 'documents'))->toBeTrue();
        
        // 2. Get original document
        $originalDoc = $this->storage->getDocument($document, 'documents');
        expect($originalDoc)->not->toBeNull();
        
        $originalPath = $this->storage->getPath($originalDoc);
        expect(file_exists($originalPath))->toBeTrue();
        
        // 3. Simulate signing process - store signed document
        $signedFile = UploadedFile::fake()->create('signed.pdf', 250);
        $tempPath = $signedFile->store('temp', 'local');
        $signedPath = Storage::disk('local')->path($tempPath);
        
        $signedMedia = $this->storage->storeDocument(
            $document, // Pass Document, adapter finds ProcessorExecution
            $signedPath,
            'signed_documents',
            [
                'transaction_id' => 'EKYC-TEST-123',
                'signed_at' => now()->toIso8601String(),
            ]
        );
        
        expect($signedMedia)->toBeInstanceOf(Media::class)
            ->and($signedMedia->getCustomProperty('transaction_id'))->toBe('EKYC-TEST-123');
        
        // 4. Verify signed document is stored
        expect($this->storage->hasDocument($execution, 'signed_documents'))->toBeTrue();
        
        // 5. Retrieve signed document
        $retrievedMedia = $this->storage->getDocument($execution, 'signed_documents');
        expect($retrievedMedia)->toBeInstanceOf(Media::class);
        
        $retrievedPath = $this->storage->getPath($retrievedMedia);
        expect(file_exists($retrievedPath))->toBeTrue();
        
        $retrievedUrl = $this->storage->getUrl($retrievedMedia);
        expect($retrievedUrl)->toBeString();
    });
});
