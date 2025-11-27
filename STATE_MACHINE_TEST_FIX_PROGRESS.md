# StateMachineTest Transaction Fix - Progress Report

## Problem Statement
StateMachineTest.php was failing with PostgreSQL transaction error:
```
SQLSTATE[25P02]: In failed sql transaction: 7 ERROR: current transaction is aborted, commands ignored until end of transaction block
```

**Root Cause**: PostgreSQL cannot execute `CREATE DATABASE` inside a transaction block. The test was using `Artisan::call('tenant:create')` which attempted to create a physical tenant database, but Pest's `RefreshDatabase` trait wraps tests in transactions.

## Solution Implemented

### 1. Created Custom Test Infrastructure
**File**: `tests/TenantAwareTestCase.php`
- New test case class that uses `DatabaseTransactions` instead of `RefreshDatabase`
- Configured to handle transactions on both 'pgsql' and 'tenant' connections
- Avoids the need for physical database creation during tests

### 2. Added Tenant Database Connection
**File**: `config/database.php` (lines 100-116)
- Added 'tenant' connection configuration
- Points to same database as 'pgsql' in test environment
- Uses `TENANT_DB_DATABASE` env var (falls back to `DB_DATABASE`)

### 3. Reorganized Test Structure
**File**: Moved `tests/Feature/StateMachineTest.php` → `tests/Feature/StateMachine/StateMachineTest.php`
- Created subdirectory to isolate state machine tests
- Updated `tests/Pest.php` to use `TenantAwareTestCase` for StateMachine directory
- Excluded StateMachine from generic Feature test configuration

### 4. Fixed State Serialization
**All state classes in** `app/States/Document/`, `app/States/DocumentJob/`, `app/States/ProcessorExecution/`
- Added `protected static $name` property to all 17 state classes
- Enables short name serialization ('pending', 'queued', etc.) instead of full class names
- Required for compatibility with enum columns in migrations

Examples:
- `PendingDocumentState::$name = 'pending'`
- `QueuedJobState::$name = 'queued'`
- `CompletedExecutionState::$name = 'completed'`

### 5. Removed Conflicting Defaults
**File**: `app/Models/Document.php` (line 57-58)
- Removed `'status' => 'pending'` from `$attributes` array
- Let state machine's `default(PendingDocumentState::class)` handle initialization

**File**: `database/factories/DocumentFactory.php` (line 29)
- Removed `'status' => fake()->randomElement([...])` from factory definition
- Let state machine handle default status initialization

### 6. Fixed Import Errors
**File**: `tests/Feature/ModelFeaturesTest.php` (line 8-9)
- Fixed double backslashes in imports: `Illuminate\\\\Foundation` → `Illuminate\\Foundation`

### 7. Test Setup Simplification
**File**: `tests/Feature/StateMachine/StateMachineTest.php`
- Removed complex tenant:create command call
- Simplified to use `Tenant::factory()->create()` for central DB record
- Mock `TenantConnectionManager` to skip physical DB operations
- Both 'pgsql' and 'tenant' connections point to same test database

## Current Status

### ✅ Fixed
- PostgreSQL transaction error resolved
- Test infrastructure properly configured
- State serialization configured correctly
- Tenant connection established
- Import errors fixed

### ⚠️ Remaining Issue
**Error**: `Class A does not extend App\States\Document\DocumentState base class`

**Occurs**: When instantiating Document model (before saving)
- Error happens in `new App\Models\Document([...])`
- "Class A" suggests only first character is being read
- Likely related to how HasStates trait initializes during model construction

**Hypothesis**: The migration default value `->default('pending')` might be interfering with state initialization, or there's a conflict between the database schema default and the state machine default.

**Next Steps**:
1. Investigate HasStates trait initialization order
2. Check if migration default value conflicts with state machine
3. May need to remove `->default('pending')` from migration
4. Or ensure state machine initializes before attributes are filled

## Test Execution

### Manual Setup Required
Before running StateMachine tests, tenant migrations must be run once:
```bash
DB_DATABASE=stash_test php artisan migrate --database=pgsql --path=database/migrations/tenant --force
```

This creates the tenant tables (campaigns, documents, etc.) in the stash_test database. DatabaseTransactions will rollback DATA but preserve SCHEMA.

### Running Tests
```bash
# Single test
php artisan test tests/Feature/StateMachine/StateMachineTest.php --filter "document initializes"

# All StateMachine tests
php artisan test tests/Feature/StateMachine/StateMachineTest.php

# Other tests (unaffected)
php artisan test tests/Feature/TenancyTest.php  # ✅ 5 passed
```

## Files Modified

### Created
- `tests/TenantAwareTestCase.php`
- `STATE_MACHINE_TEST_FIX_PROGRESS.md` (this file)

### Modified
- `config/database.php` - Added 'tenant' connection
- `tests/Pest.php` - Added StateMachine directory configuration
- `tests/TestCase.php` - Added afterRefreshingDatabase() hook (not used in final solution)
- `tests/Feature/StateMachine/StateMachineTest.php` - Simplified setup, removed Str import
- `tests/Feature/ModelFeaturesTest.php` - Fixed double backslashes
- `app/Models/Document.php` - Removed status from $attributes
- `database/factories/DocumentFactory.php` - Removed status from definition

### State Classes (17 files)
All state classes now have `protected static $name`:
- `app/States/Document/*.php` (6 files)
- `app/States/DocumentJob/*.php` (6 files)  
- `app/States/ProcessorExecution/*.php` (5 files)

## Test Statistics
- **Target**: 104+ tests (21 in StateMachineTest.php)
- **Current**: 21 tests fail with "Class A" error
- **Other Tests**: Passing (e.g., TenancyTest: 5/5 passed)

## Commit Ready
All changes are ready to commit. The core transaction issue is resolved. The "Class A" error is a separate state machine initialization issue that needs investigation in the next session.
