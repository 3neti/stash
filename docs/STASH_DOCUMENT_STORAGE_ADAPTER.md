# Stash Document Storage Adapter

**Status**: ✅ READY - Fully implemented and tested

## Overview

The `StashDocumentStorage` adapter bridges Stash's multi-storage architecture with the HyperVerge package's `DocumentStoragePort` interface, enabling the ElectronicSignatureProcessor to work seamlessly with Stash's existing document management system.

## Architecture

### Stash Storage Pattern

Stash uses two different storage patterns depending on the entity:

1. **Document Model** - Direct Laravel Storage
   - Fields: `storage_disk`, `storage_path`
   - Storage: Direct files via `Storage::disk($disk)->path($path)`
   - Use case: Original uploaded documents

2. **ProcessorExecution Model** - Spatie MediaLibrary
   - Methods: `addMedia()`, `getFirstMedia()`, `hasMedia()`
   - Collections: `signed_documents`, `kyc_id_cards`, `kyc_selfies`, etc.
   - Use case: Processor-generated artifacts (signed PDFs, KYC images, etc.)

### Adapter Responsibilities

The adapter intelligently routes storage operations:

- **Reading** original documents → Document model (direct Storage)
- **Storing** signed documents → ProcessorExecution model (MediaLibrary)

## Implementation

### File

`app/Services/StashDocumentStorage.php`

### Interface Compliance

Implements `LBHurtado\HyperVerge\Contracts\DocumentStoragePort`:

```php
interface DocumentStoragePort {
    public function storeDocument(Model $model, string $filePath, string $collection, array $customProperties = []): mixed;
    public function getDocument(Model $model, string $collection): mixed;
    public function getPath(mixed $media): string;
    public function getUrl(mixed $media): string;
    public function hasDocument(Model $model, string $collection): bool;
    public function deleteDocument(Model $model, string $collection): bool;
}
```

### Key Design Decisions

#### 1. Anonymous Object for Document Storage

When retrieving original documents, the adapter returns an anonymous object (not Spatie Media) that provides `getPath()` and `getUrl()` methods:

```php
return new class($model) {
    private Document $document;
    
    public function __construct(Document $document) {
        $this->document = $document;
    }
    
    public function getPath(): string {
        return Storage::disk($this->document->storage_disk)
            ->path($this->document->storage_path);
    }
    
    public function getUrl(): string {
        return Storage::disk($this->document->storage_disk)
            ->url($this->document->storage_path);
    }
};
```

**Why?** This provides a consistent interface for HyperVerge's `MarkDocumentWithKYC` action without forcing Document to implement MediaLibrary.

#### 2. ProcessorExecution Lookup

When storing signed documents via a Document model reference, the adapter automatically finds the associated ProcessorExecution:

```php
if ($model instanceof Document) {
    $execution = $model->documentJob?->processorExecutions()
        ->whereHas('processor', fn($q) => $q->where('slug', 'electronic-signature'))
        ->latest()
        ->first();
    
    return $execution->addMedia($filePath)
        ->withCustomProperties($customProperties)
        ->toMediaCollection($collection);
}
```

**Why?** The HyperVerge package expects to pass the Document model, but artifacts should be stored in ProcessorExecution. This automatic lookup maintains the correct ownership model.

#### 3. Singleton Binding

Registered in `AppServiceProvider`:

```php
$this->app->singleton(
    DocumentStoragePort::class,
    StashDocumentStorage::class
);
```

**Why?** The adapter is stateless and should be shared across requests.

## Usage

### In ElectronicSignatureProcessor

```php
use LBHurtado\HyperVerge\Actions\MarkDocumentWithKYC;
use LBHurtado\HyperVerge\Contracts\DocumentStoragePort;

class ElectronicSignatureProcessor implements ProcessorInterface 
{
    public function handle(Document $document, ProcessorConfigData $config, ProcessorContextData $context): ProcessorResultData
    {
        // ...
        
        // HyperVerge action uses injected DocumentStoragePort
        $result = MarkDocumentWithKYC::run(
            model: $document,  // Adapter finds ProcessorExecution automatically
            transactionId: $config->config['transaction_id'],
            qrMode: 'bottom-right'
        );
        
        // Signed PDF is now stored in ProcessorExecution media collection
        return ProcessorResultData::success([
            'signed_document_path' => $result['signed_path'],
            'qr_code_position' => 'bottom-right',
        ]);
    }
}
```

## Testing

### Test Coverage

16 tests covering all interface methods and edge cases:

```bash
php artisan test tests/Feature/Services/StashDocumentStorageTest.php
```

**Test categories**:
1. Interface compliance (implements all required methods)
2. Document model operations (getDocument, getPath, getUrl)
3. ProcessorExecution model operations (media storage/retrieval)
4. Automatic ProcessorExecution lookup from Document
5. Error handling (missing ProcessorExecution, invalid models)
6. Full e-signature workflow simulation

### Test Results

```
PASS  Tests\Feature\Services\StashDocumentStorageTest
✓ implements DocumentStoragePort interface
✓ has all required interface methods
✓ returns object with getPath() for existing document
✓ getPath() returns absolute file path
✓ returns null for non-existent document
✓ returns Media object for existing artifact
✓ stores document in ProcessorExecution media collection
✓ stores custom properties with document
✓ stores in ProcessorExecution when given Document model
✓ throws exception when no ProcessorExecution exists
✓ returns true for existing Document file
✓ returns false for deleted Document file
✓ returns true for existing ProcessorExecution media
✓ deletes Document file from storage
✓ deletes ProcessorExecution media
✓ simulates complete e-signature document lifecycle

Tests:  16 passed (44 assertions)
```

## Benefits

### 1. Clean Architecture

- **Separation of concerns**: Original documents vs processor artifacts
- **No model pollution**: Document doesn't need to implement MediaLibrary
- **Consistent interface**: HyperVerge package works with any storage pattern

### 2. Maintainability

- **Centralized storage logic**: All storage routing in one adapter
- **Type safety**: Interface contract enforced by PHP
- **Testable**: Pure logic, no hidden dependencies

### 3. Extensibility

- **Future storage patterns**: Easy to add new storage backends
- **Flexible routing**: Can route to different models based on conditions
- **Metadata support**: Custom properties pass through transparently

## Future Enhancements

### Potential Improvements

1. **Storage disk configuration**: Allow configuring which disk to use per processor
2. **Automatic cleanup**: Integrate with Stash's retention policy system
3. **Versioning**: Track document versions (v1, v2, v3) in media collections
4. **Audit trail**: Log all storage operations for compliance

### Not Needed (Already Handled)

- ~~Consolidate Document storage to use MediaLibrary~~ - Not recommended; direct Storage is simpler for original uploads
- ~~Add caching layer~~ - Laravel Storage already caches disk instances
- ~~Support cloud storage~~ - Already works via Laravel Storage configuration

## Related Documentation

- **ENF Setup Guide**: `docs/ENF_SETUP_GUIDE.md` - Testing the full e-signature workflow
- **Processor Dependencies**: `docs/PROCESSOR_DEPENDENCIES.md` - How ElectronicSignatureProcessor depends on EKycVerificationProcessor
- **HyperVerge Integration**: `packages/hyperverge/README.md` - Understanding the package interfaces

## Troubleshooting

### "No document found for model: App\Models\Document"

**Cause**: HyperVerge package expected Document to have MediaLibrary, but Document uses direct Storage.

**Solution**: ✅ Fixed by `StashDocumentStorage` adapter returning anonymous object with `getPath()`

### "Cannot store signed document: No ProcessorExecution found"

**Cause**: Attempting to store signed document for a Document that has no associated ProcessorExecution.

**Solution**: Ensure Document has been processed through ElectronicSignatureProcessor workflow (requires DocumentJob and ProcessorExecution)

### Storage disk not found

**Cause**: `storage_disk` field references a disk not configured in `config/filesystems.php`.

**Solution**: Verify disk configuration or update Document record to use valid disk (e.g., `'public'`, `'local'`)

---

**Implementation Date**: December 2024  
**Test Coverage**: 16 tests, 44 assertions, 100% pass rate  
**Dependencies**: Laravel Storage, Spatie MediaLibrary, HyperVerge package
