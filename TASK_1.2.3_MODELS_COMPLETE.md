# Task 1.2.3: Eloquent Models - COMPLETE ✅

**Date**: 2025-11-27  
**Status**: All 8 tenant-scoped models created  
**Location**: `app/Models/`

---

## Models Created

### 1. Campaign Model ✅
**File**: `app/Models/Campaign.php`  
**Lines**: 194

**Features**:
- ✅ ULID primary key auto-generation
- ✅ BelongsToTenant trait (tenant database)
- ✅ Pipeline config JSON casting
- ✅ Encrypted credentials field
- ✅ Soft deletes
- ✅ Status scopes (active, draft, published)
- ✅ Helper methods (publish, pause, archive, isActive, isPublished)
- ✅ Relationships: documents, documentJobs, usageEvents
- ✅ Accessor: processor_count from pipeline_config

**Key Methods**:
- `publish()` - Publish and activate campaign
- `pause()` - Pause campaign
- `archive()` - Archive campaign
- `isActive()` - Check if active
- `isPublished()` - Check if published

---

### 2. Document Model ✅
**File**: `app/Models/Document.php`  
**Lines**: 190

**Features**:
- ✅ ULID + UUID (public-facing) generation
- ✅ BelongsToTenant trait
- ✅ Storage helpers (getUrl, getContents, deleteFile, fileExists)
- ✅ Status transitions (markCompleted, markFailed)
- ✅ Metadata & processing_history JSON casting
- ✅ Soft deletes
- ✅ File size formatting
- ✅ Retry count tracking
- ✅ Processing history tracking

**Key Methods**:
- `markCompleted()` - Mark as completed
- `markFailed(string $error)` - Mark as failed
- `incrementRetries()` - Increment retry count
- `getUrl()` - Get file URL
- `get Contents()` - Get file contents
- `addProcessingHistory()` - Add stage to history

**Storage Features**:
- Multi-disk support (S3, local)
- SHA-256 hash for integrity
- Formatted file size accessor

---

### 3. DocumentJob Model ✅
**File**: `app/Models/DocumentJob.php`  
**Lines**: 163

**Features**:
- ✅ ULID + UUID generation
- ✅ BelongsToTenant trait
- ✅ State machine (start, complete, fail)
- ✅ Pipeline instance management
- ✅ Error log tracking
- ✅ Attempt/retry logic
- ✅ Current processor index tracking

**Key Methods**:
- `start()` - Start job execution
- `complete()` - Mark job complete
- `fail(string $error)` - Mark job failed with error log
- `incrementAttempts()` - Track retry attempts
- `advanceProcessor()` - Move to next processor
- `canRetry()` - Check if retries available

**State Machine**:
- pending → running → completed
- pending → running → failed (with retries)

---

### 4. Processor Model ✅
**File**: `app/Models/Processor.php`  
**Lines**: 96

**Features**:
- ✅ ULID primary key
- ✅ BelongsToTenant trait
- ✅ Category enum (ocr, classification, extraction, etc.)
- ✅ Config schema JSON
- ✅ System vs custom processors
- ✅ Active/inactive flag
- ✅ Version tracking

**Key Scopes**:
- `active()` - Active processors only
- `system()` - System processors
- `custom()` - Custom processors
- `byCategory(string $category)` - Filter by category

**Processor Registry**:
- Class name (FQN) for processor implementation
- Config schema for validation
- Documentation URL support

---

### 5. ProcessorExecution Model ✅
**File**: `app/Models/ProcessorExecution.php`  
**Lines**: 132

**Features**:
- ✅ ULID primary key
- ✅ BelongsToTenant trait
- ✅ Token usage tracking
- ✅ Cost credits tracking
- ✅ Duration tracking (milliseconds)
- ✅ Input/output data JSON
- ✅ Config snapshot

**Key Methods**:
- `start()` - Start execution timer
- `complete(array $output, int $tokens, int $cost)` - Complete with metrics
- `fail(string $error)` - Mark failed with duration

**Metrics Tracked**:
- Duration in milliseconds
- Tokens used (AI APIs)
- Cost in credits
- Input/output data

---

### 6. Credential Model ✅
**File**: `app/Models/Credential.php`  
**Lines**: 169

**Features**:
- ✅ ULID primary key
- ✅ BelongsToTenant trait
- ✅ Encrypted value field (AES-256)
- ✅ Hierarchical scoping (system > subscriber > campaign > processor)
- ✅ Expiration support
- ✅ Last used tracking
- ✅ Soft deletes

**Hierarchy Resolution**:
```php
Credential::resolve(
    key: 'openai_api_key',
    processorId: $id,      // Try processor scope first
    campaignId: $id,       // Then campaign scope
    subscriberId: $id      // Then subscriber scope
);                          // Finally system scope
```

**Key Methods**:
- `resolve()` - Hierarchical credential lookup
- `markUsed()` - Track usage
- `isExpired()` - Check expiration
- `isActive()` - Check active & not expired

**Scopes**:
1. **system** - Global credentials (fallback)
2. **subscriber** - Tenant-level
3. **campaign** - Campaign-specific
4. **processor** - Processor-specific (highest priority)

---

### 7. UsageEvent Model ✅
**File**: `app/Models/UsageEvent.php`  
**Lines**: 113

**Features**:
- ✅ ULID primary key
- ✅ BelongsToTenant trait
- ✅ Append-only (no updates/deletes)
- ✅ Event types (upload, storage, processor_execution, ai_task, etc.)
- ✅ Units & cost tracking
- ✅ Aggregation helpers

**Key Methods**:
- `totalCredits($start, $end)` - Total credits in period
- `breakdownByType($start, $end)` - Usage by event type

**Aggregation Queries**:
- Sum credits by period
- Group by event type
- Period filtering

**Event Types**:
- `upload` - Document upload
- `storage` - Storage usage
- `processor_execution` - Processor run
- `ai_task` - AI API call
- `connector_call` - External API
- `agent_tool` - Agent tool usage

---

### 8. AuditLog Model ✅
**File**: `app/Models/AuditLog.php`  
**Lines**: 128

**Features**:
- ✅ ULID primary key
- ✅ BelongsToTenant trait
- ✅ **Immutable** (prevents updates & deletes)
- ✅ Polymorphic auditable relationship
- ✅ Old/new values tracking
- ✅ IP address & user agent capture
- ✅ Tag support (JSON)

**Key Methods**:
- `log()` - Static helper to create audit entry

**Usage Example**:
```php
AuditLog::log(
    auditableType: Campaign::class,
    auditableId: $campaign->id,
    event: 'published',
    oldValues: ['status' => 'draft'],
    newValues: ['status' => 'active'],
    userId: auth()->id(),
    tags: ['campaign', 'publish']
);
```

**Immutability**:
- Updates return `false` (blocked)
- Deletes return `false` (blocked)
- Append-only for compliance

---

## Common Features Across All Models

### 1. ULID Primary Keys
All models use ULID (Universally Unique Lexicographically Sortable Identifier):
```php
protected static function boot(): void
{
    parent::boot();
    
    static::creating(function ($model) {
        if (empty($model->id)) {
            $model->id = (string) Str::ulid();
        }
    });
}
```

**Benefits**:
- Sortable by creation time
- 26 characters (vs UUID's 36)
- URL-safe
- No collisions

### 2. BelongsToTenant Trait
All models automatically use the tenant database:
```php
use App\Tenancy\Traits\BelongsToTenant;

class Campaign extends Model
{
    use BelongsToTenant; // Automatically uses 'tenant' connection
}
```

### 3. JSON Casting
Configuration and metadata fields use array casting:
```php
protected $casts = [
    'pipeline_config' => 'array',
    'metadata' => 'array',
    'settings' => 'array',
];
```

### 4. Encryption
Sensitive fields use Laravel's encrypted accessors:
```php
protected function credentials(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
        set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
    );
}
```

---

## Relationships Graph

```
Campaign
├── documents (HasMany → Document)
├── documentJobs (HasMany → DocumentJob)
└── usageEvents (HasMany → UsageEvent)

Document
├── campaign (BelongsTo → Campaign)
├── user (BelongsTo → User)
├── documentJobs (HasMany → DocumentJob)
└── usageEvents (HasMany → UsageEvent)

DocumentJob
├── campaign (BelongsTo → Campaign)
├── document (BelongsTo → Document)
└── processorExecutions (HasMany → ProcessorExecution)

Processor
└── processorExecutions (HasMany → ProcessorExecution)

ProcessorExecution
├── documentJob (BelongsTo → DocumentJob)
└── processor (BelongsTo → Processor)

Credential
└── (scoped by type/id, no direct relationships)

UsageEvent
├── campaign (BelongsTo → Campaign)
├── document (BelongsTo → Document)
└── documentJob (BelongsTo → DocumentJob)

AuditLog
├── user (BelongsTo → User)
└── auditable (MorphTo → Any model)
```

---

## Testing Status

### Models Created: 8/8 ✅
1. ✅ Campaign
2. ✅ Document
3. ✅ DocumentJob
4. ✅ Processor
5. ✅ ProcessorExecution
6. ✅ Credential
7. ✅ UsageEvent
8. ✅ AuditLog

### Next Steps: Unit Tests
Create comprehensive unit tests for:
- Model relationships
- Scopes and query builders
- Helper methods
- State transitions
- Encryption/decryption
- Immutability (AuditLog)
- Multi-tenant isolation

---

## Code Statistics

| Model | Lines | Methods | Relationships | Scopes |
|-------|-------|---------|---------------|--------|
| Campaign | 194 | 11 | 3 | 3 |
| Document | 190 | 15 | 4 | 3 |
| DocumentJob | 163 | 11 | 3 | 3 |
| Processor | 96 | 4 | 1 | 4 |
| ProcessorExecution | 132 | 7 | 2 | 2 |
| Credential | 169 | 8 | 0 | 4 |
| UsageEvent | 113 | 7 | 3 | 2 |
| AuditLog | 128 | 8 | 2 | 5 |
| **TOTAL** | **1,185** | **71** | **18** | **26** |

---

## Key Design Decisions

### 1. Tenant-Scoped by Default
All models use `BelongsToTenant` trait to automatically query the tenant database. No cross-tenant data leaks possible.

### 2. ULID over Auto-Increment
Sortable, distributed-safe, and more secure than sequential IDs.

### 3. JSON for Flexibility
Pipeline configs, metadata, and settings use JSON for schema flexibility during MVP development.

### 4. Encryption for Secrets
Credentials and sensitive campaign data encrypted at rest using Laravel's Crypt facade (AES-256).

### 5. Immutable Audit Log
Compliance-friendly append-only audit trail that cannot be tampered with.

### 6. State Machines
Document and DocumentJob models have explicit state transition methods for clarity.

### 7. Hierarchical Credentials
Four-level hierarchy (system → subscriber → campaign → processor) for flexible credential management.

---

## Next: Task 1.2.4 - Factories

Create factories for all models to generate test data:
1. CampaignFactory
2. DocumentFactory
3. DocumentJobFactory
4. ProcessorFactory
5. ProcessorExecutionFactory
6. CredentialFactory
7. UsageEventFactory
8. AuditLogFactory

---

**Task 1.2.3 Status**: ✅ COMPLETE  
**Ready for**: Task 1.2.4 (Factories) or Task 1.2.6 (Model Tests)
