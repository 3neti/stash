# TODO: Task 1.2.6 - State Machine Integration

**Package**: `spatie/laravel-model-states`  
**Priority**: HIGH - Do this BEFORE writing tests  
**Estimated Time**: 2-3 hours

---

## Installation

```bash
composer require spatie/laravel-model-states
php artisan vendor:publish --tag="model-states-config"
php artisan vendor:publish --tag="model-states-migrations"
php artisan migrate
```

---

## Models to Refactor

### 1. Document Model
**Current**: String status field with manual transitions  
**New**: State machine with validated transitions

**States**:
- `PendingDocumentState` (initial)
- `QueuedDocumentState`
- `ProcessingDocumentState`
- `CompletedDocumentState` (final)
- `FailedDocumentState` (final)
- `CancelledDocumentState` (final)

**Transitions**:
- pending → queued
- queued → processing
- processing → completed
- processing → failed
- any → cancelled

**Files to Create**:
```
app/States/Document/
├── DocumentState.php (abstract)
├── PendingDocumentState.php
├── QueuedDocumentState.php
├── ProcessingDocumentState.php
├── CompletedDocumentState.php
├── FailedDocumentState.php
└── CancelledDocumentState.php
```

---

### 2. DocumentJob Model
**Current**: String status field with manual transitions  
**New**: State machine with retry logic

**States**:
- `PendingJobState` (initial)
- `QueuedJobState`
- `RunningJobState`
- `CompletedJobState` (final)
- `FailedJobState` (final)
- `CancelledJobState` (final)

**Transitions**:
- pending → queued
- queued → running
- running → completed
- running → failed (with retry check)
- failed → queued (if retries available)
- any → cancelled

**Files to Create**:
```
app/States/DocumentJob/
├── DocumentJobState.php (abstract)
├── PendingJobState.php
├── QueuedJobState.php
├── RunningJobState.php
├── CompletedJobState.php
├── FailedJobState.php
└── CancelledJobState.php
```

---

### 3. ProcessorExecution Model
**Current**: String status field with manual transitions  
**New**: State machine with metrics

**States**:
- `PendingExecutionState` (initial)
- `RunningExecutionState`
- `CompletedExecutionState` (final)
- `FailedExecutionState` (final)
- `SkippedExecutionState` (final)

**Transitions**:
- pending → running
- running → completed
- running → failed
- pending → skipped (conditional)

**Files to Create**:
```
app/States/ProcessorExecution/
├── ProcessorExecutionState.php (abstract)
├── PendingExecutionState.php
├── RunningExecutionState.php
├── CompletedExecutionState.php
├── FailedExecutionState.php
└── SkippedExecutionState.php
```

---

## Implementation Steps

### Step 1: Install Package
```bash
composer require spatie/laravel-model-states
php artisan vendor:publish --tag="model-states-config"
php artisan vendor:publish --tag="model-states-migrations"
php artisan migrate
```

### Step 2: Create State Classes
For each model, create abstract base state and concrete states.

**Example** (`app/States/Document/DocumentState.php`):
```php
<?php

namespace App\States\Document;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class DocumentState extends State
{
    abstract public function color(): string;
    
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingDocumentState::class)
            ->allowTransition(PendingDocumentState::class, QueuedDocumentState::class)
            ->allowTransition(QueuedDocumentState::class, ProcessingDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, CompletedDocumentState::class)
            ->allowTransition(ProcessingDocumentState::class, FailedDocumentState::class)
            ->allowTransition([
                PendingDocumentState::class,
                QueuedDocumentState::class,
                ProcessingDocumentState::class
            ], CancelledDocumentState::class);
    }
}
```

### Step 3: Update Models
Add `HasStates` trait and configure state field:

```php
use Spatie\ModelStates\HasStates;

class Document extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, HasStates;
    
    protected function registerStates(): void
    {
        $this->addState('status', DocumentState::class);
    }
    
    protected $casts = [
        'status' => DocumentState::class,
        // ... other casts
    ];
}
```

### Step 4: Refactor Transitions
Replace manual status updates with state transitions:

**Before**:
```php
$document->update([
    'status' => 'completed',
    'processed_at' => now(),
]);
```

**After**:
```php
$document->status->transitionTo(CompletedDocumentState::class);
```

### Step 5: Add Transition Events
Create events for state transitions:

```php
// app/States/Document/CompletedDocumentState.php
public function __construct(Document $document)
{
    parent::__construct($document);
    
    $document->update(['processed_at' => now()]);
    
    event(new DocumentCompletedEvent($document));
}
```

---

## Benefits

1. **Type Safety** - State classes instead of strings
2. **Validated Transitions** - Can't skip states or make invalid changes
3. **Event Driven** - Automatic events on state changes
4. **History Tracking** - Built-in state change history
5. **Cleaner Code** - Business logic in state classes
6. **Better Testing** - Test state transitions in isolation
7. **Meta-Campaign Ready** - State machine can be understood and modified by AI

---

## Testing Strategy

After refactoring:

1. **Transition Tests** - Test all allowed transitions
2. **Invalid Transition Tests** - Test blocked transitions throw exceptions
3. **Event Tests** - Test events fire on state changes
4. **Timestamp Tests** - Test automatic timestamp updates
5. **History Tests** - Test state history is recorded

---

## Rollback Plan

If issues arise:
1. Remove `HasStates` trait from models
2. Keep state classes for future use
3. Revert to string status fields
4. Uninstall package: `composer remove spatie/laravel-model-states`

---

**Status**: ⏳ PENDING - Do in Task 1.2.6  
**Reminder**: Install BEFORE writing model tests!
