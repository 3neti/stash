# Processor Dependencies System

## Overview

The Stash platform now supports **processor dependencies**, allowing you to enforce that certain processors must complete before others can execute. This is particularly useful for workflows like Electronic Notarization Framework (ENF) where KYC verification must happen before document signing.

## Implementation

### Database Schema

The `processors` table includes:
- **`dependencies`** (JSON): Array of processor slugs that must run before this processor
- **`category`** enum: Now includes `'signing'` category for signature processors

```sql
CREATE TABLE processors (
    ...
    category ENUM(..., 'signing', ...),
    config_schema JSON,
    output_schema JSON,
    dependencies JSON,  -- ['ekyc-verification', 'other-processor']
    ...
);
```

### Model Integration

The `Processor` model uses the `ProcessorDependencies` trait:

```php
use App\Models\Concerns\ProcessorDependencies;

class Processor extends Model
{
    use ProcessorDependencies;
    
    protected $casts = [
        'dependencies' => 'array',
    ];
}
```

### Dependency Validation Methods

#### 1. Check Dependencies

```php
$processor = Processor::where('slug', 'electronic-signature')->first();

$result = $processor->checkDependencies($jobId);
// Returns: ['satisfied' => bool, 'missing' => array]

if (!$result['satisfied']) {
    echo "Missing: " . implode(', ', $result['missing']);
}
```

#### 2. Assert Dependencies (throws exception)

```php
try {
    $processor->assertDependenciesSatisfied($jobId);
} catch (\RuntimeException $e) {
    // "Processor 'electronic-signature' requires these processors to run first: ekyc-verification"
}
```

#### 3. Get Dependency Tree

```php
$tree = $processor->getDependencyTree();
// Returns array of Processor models in execution order
// Example: [EKycProcessor, ElectronicSignatureProcessor]
```

## ElectronicSignatureProcessor

### Overview

The `ElectronicSignatureProcessor` signs PDF documents with eKYC verification data using the HyperVerge package.

**Category**: `signing`  
**Dependencies**: `['ekyc-verification']`

### Features

✅ **PKCS#7 Digital Signatures** - Tamper-proof document signing  
✅ **QR Code Watermarks** - Instant verification via QR scan  
✅ **ID Image Stamps** - Overlay signer's ID photo + metadata  
✅ **Blockchain Timestamping** - Immutable proof via OpenTimestamps  
✅ **Integration with Contact KYC** - Automatically pulls approved KYC data

### Configuration

```json
{
  "transaction_id": "EKYC-1234567890-1234",  // Required: from EKycVerificationProcessor
  "tile": 1,                                 // Optional: signature position (1-9)
  "logo_path": "/path/to/logo.png",         // Optional: custom logo
  "metadata": {                              // Optional: additional metadata
    "department": "Legal",
    "document_type": "Contract"
  }
}
```

### Output Schema

```json
{
  "signed_document": {
    "media_id": 123,
    "file_name": "contract_signed.pdf",
    "size": 245678,
    "mime_type": "application/pdf",
    "url": "https://..."
  },
  "stamp": {
    "media_id": 124,
    "file_name": "signature_stamp.png",
    "url": "https://..."
  },
  "transaction_id": "EKYC-...",
  "verification_url": "https://app.test/documents/verify/...",
  "signer_info": {
    "contact_id": "01...",
    "name": "John Doe",
    "email": "john@example.com",
    "mobile": "+639171234567",
    "kyc_status": "approved",
    "kyc_completed_at": "2024-12-05T00:00:00Z"
  },
  "signature_timestamp": "2024-12-05T00:48:00Z",
  "tile_position": 1,
  "metadata": {...}
}
```

### Validation Rules

1. **Requires PDF document** - Only `application/pdf` mime type accepted
2. **Requires approved KYC** - `transaction_id` must have `kyc_status = 'approved'`
3. **Requires EKycVerificationProcessor** - Enforced by `dependencies: ['ekyc-verification']`

### Error Messages

**Missing transaction_id**:
```
RuntimeException: transaction_id is required for electronic signature
```

**KYC not approved**:
```
RuntimeException: KYC verification not approved for transaction: EKYC-123. 
Run eKYC Verification processor first.
```

**Dependency not satisfied** (enforced by workflow):
```
RuntimeException: Processor 'electronic-signature' requires these processors to run first: ekyc-verification
```

## Workflow Integration

### Example: ENF Basic Notarization Campaign

```php
use App\Models\Campaign;
use App\Models\Processor;

$campaign = Campaign::create([
    'name' => 'ENF Basic Notarization',
    'type' => 'custom',
    'settings' => [
        'workflow' => 'sequential', // Run processors in order
    ],
]);

// Add processors in order
$ekycProcessor = Processor::where('slug', 'ekyc-verification')->first();
$signatureProcessor = Processor::where('slug', 'electronic-signature')->first();

$campaign->processors()->attach($ekycProcessor->id, [
    'order' => 1,
    'config' => [
        'country' => 'PH',
        'transaction_id_prefix' => 'ENF',
    ],
]);

$campaign->processors()->attach($signatureProcessor->id, [
    'order' => 2,
    'config' => [
        'transaction_id' => '{{ekyc-verification.transaction_id}}', // From previous processor
        'tile' => 1,
    ],
]);
```

### Automatic Dependency Resolution

The workflow system automatically:
1. Checks `dependencies` array before executing a processor
2. Validates all dependencies have `status = 'completed'`
3. Throws exception if dependencies are not satisfied
4. Resolves execution order based on dependency tree

## Usage in Campaigns

### Step 1: User Uploads Document

```bash
POST /api/documents/upload
Content-Type: multipart/form-data

file: contract.pdf
campaign_id: 01...
```

### Step 2: EKycVerificationProcessor Runs

- Generates transaction ID: `ENF-1733363280-4567`
- Creates onboarding link
- User completes KYC (selfie + ID scan)
- HyperVerge webhook updates `Contact` with `kyc_status = 'approved'`

### Step 3: ElectronicSignatureProcessor Runs Automatically

- Dependency check: ✅ `ekyc-verification` completed
- Retrieves approved `Contact` by `transaction_id`
- Signs PDF with KYC data (ID photo, name, etc.)
- Adds QR watermark for instant verification
- Returns signed document + stamp

### Step 4: User Downloads Signed Document

```bash
GET /api/documents/{uuid}/signed
Authorization: Bearer {token}
```

## Testing

### Unit Tests

```bash
php artisan test tests/Unit/Processors/ElectronicSignatureProcessorTest.php
```

**Test Cases**:
- ✅ Requires `transaction_id` in config
- ✅ Requires approved KYC
- ✅ Signs document with KYC data
- ✅ Only accepts PDF documents
- ✅ Has valid output schema

### Feature Tests

```bash
php artisan test tests/Feature/DeadDrop/ElectronicSignatureWorkflowTest.php
```

**Test Cases**:
- ✅ End-to-end workflow: Upload → KYC → Sign
- ✅ Dependency validation enforced
- ✅ Signed document stored in media library
- ✅ Contact linked to signed document

## Seeder Configuration

The `ProcessorSeeder` includes the ElectronicSignatureProcessor:

```php
[
    'name' => 'Electronic Signature',
    'slug' => 'electronic-signature',
    'class_name' => 'App\\Processors\\ElectronicSignatureProcessor',
    'category' => 'signing',
    'description' => 'Sign PDF documents with eKYC verification data, QR codes, and blockchain timestamps',
    'dependencies' => ['ekyc-verification'], // ← ENFORCES EXECUTION ORDER
    'config_schema' => [...],
    'output_schema' => [...],
    'is_system' => true,
    'version' => '1.0.0',
]
```

## Benefits

### 1. **Type Safety**
- Database-enforced dependencies
- Compile-time error prevention

### 2. **Clear Workflow Definition**
- Self-documenting processor relationships
- Easy to visualize execution order

### 3. **Runtime Validation**
- Automatic dependency checks
- Helpful error messages

### 4. **Flexibility**
- Support for complex dependency trees
- Circular dependency detection

## Future Enhancements

### 1. Visual Dependency Graph
Display processor dependencies in the UI as a DAG (Directed Acyclic Graph).

### 2. Conditional Dependencies
```php
'dependencies' => [
    'if' => 'config.require_kyc === true',
    'then' => ['ekyc-verification'],
]
```

### 3. Parallel Execution
```php
'dependencies' => [
    'any_of' => ['ocr-processor-a', 'ocr-processor-b'], // Either one is sufficient
]
```

### 4. Dependency Versions
```php
'dependencies' => [
    'ekyc-verification' => '^1.0', // Semver constraints
]
```

## Related Files

- **Processor**: `app/Processors/ElectronicSignatureProcessor.php`
- **Trait**: `app/Models/Concerns/ProcessorDependencies.php`
- **Model**: `app/Models/Processor.php`
- **Migration**: `database/migrations/tenant/2025_11_27_075957_create_processors_table.php`
- **Seeder**: `database/seeders/ProcessorSeeder.php`
- **Tests**: `tests/Unit/Processors/ElectronicSignatureProcessorTest.php`

## Questions?

For implementation details or troubleshooting, see:
- `WARP.md` - General project documentation
- `ENF_ON_STASH.md` - Electronic Notarization Framework specification
