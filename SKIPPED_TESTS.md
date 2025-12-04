# Skipped Tests - Fix TODO

This document tracks tests that are currently skipped and need to be fixed in the future.

## Summary
- **Total Test Suite**: 494 tests
- **Passing**: 453 tests (91.7%)
- **Skipped**: 41 tests (8.3%)
- **Target**: 100% passing

## Test Files Requiring Fixes

### 1. DocumentUploadRouteTest (3 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Missing route implementation + ULID factory collision
**Location**: `tests/Feature/DeadDrop/DocumentUploadRouteTest.php`

**Issues**:
- Route `/campaigns/{id}/documents` POST not implemented (returns 404)
- Document factory generates duplicate ULIDs in test runs (Laravel caches ULID factory)

**Fix**:
1. Implement `DocumentController@store` method for document uploads
2. Fix ULID collision: Add randomness seed to Document factory or use `Str::ulid()` directly

---

### 2. CsvImportLocalizedValidationTest (4 tests)
**Status**: ⏸️ SKIPPED
**Reason**: QueryException - tenant_id not null violation
**Location**: `tests/Feature/DeadDrop/CsvImportLocalizedValidationTest.php`

**Issues**:
- `SQLSTATE[23502]`: Not null violation for `tenant_id` in `custom_validation_rules`
- CustomValidationRule creation missing tenant_id auto-population

**Fix**:
1. Ensure CustomValidationRule factory sets `tenant_id` correctly
2. Add `tenant_id` to CustomValidationRule's `$fillable` array
3. Use `BelongsToTenant` trait or manually set tenant_id in factory

---

### 3. CsvImportWithRegexTest (7 tests)
**Status**: ⏸️ SKIPPED  
**Reason**: Pending processor alignment
**Location**: `tests/Feature/DeadDrop/CsvImportWithRegexTest.php`

**Issues**:
- CSV processor transformations and validation schema need alignment
- Regex transformation pipeline not fully implemented

**Fix**:
1. Complete CSV processor regex transformation implementation
2. Align validation rules with transformation outputs

---

### 4. PipelineEndToEndTest (6 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Requires AI processor mocking
**Location**: `tests/Feature/DeadDrop/PipelineEndToEndTest.php`

**Issues**:
- Tests require real OpenAI API calls (OCR, Classification, Extraction)
- Missing test fixture image files

**Fix**:
1. Mock AI processor responses (use Http::fake() or processor mocks)
2. Add test fixture images to `tests/Fixtures/images/`
3. Create stub processors for testing

---

### 5. EKycVerificationProcessorTest (4 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Processor class doesn't exist
**Location**: `tests/Feature/DeadDrop/EKycVerificationProcessorTest.php`

**Issues**:
- `EKycVerificationProcessor` class not implemented yet
- Tests written ahead of implementation

**Fix**:
1. Implement `App\Processors\EKycVerificationProcessor` class
2. Integrate HyperVerge KYC API
3. Implement Contact model KYC methods

---

### 6. ContactModelTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Missing tenant_id auto-population
**Location**: `tests/Feature/DeadDrop/ContactModelTest.php`

**Issues**:
- Contact model not auto-populating `tenant_id` from `TenantContext`
- Similar to issue #2 above

**Fix**:
1. Add `BelongsToTenant` trait to Contact model
2. Ensure Contact::create() auto-sets tenant_id

---

### 7. CampaignAccessWithoutSQLSTATETest (3 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Missing Inertia component
**Location**: `tests/Feature/DeadDrop/CampaignAccessWithoutSQLSTATETest.php`

**Issues**:
- Missing `campaigns/Show.vue` Inertia component
- Route returns 404

**Fix**:
1. Create `resources/js/pages/campaigns/Show.vue`
2. Implement CampaignController@show method

---

### 8. CampaignBrowserRequestTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Same as #7 - missing Inertia component  
**Location**: `tests/Feature/DeadDrop/CampaignBrowserRequestTest.php`

**Fix**: Same as #7

---

### 9. TenancyTest (2 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Tenant database not created in test environment
**Location**: `tests/Feature/TenancyTest.php`

**Tests**:
- `test_tenant_context_switching`
- `test_tenant_context_run_with_callback`

**Fix**:
1. Update test setup to create actual tenant databases
2. Or mock `TenantConnectionManager` to avoid real DB creation

---

### 10. FeatureFlagIntegrationTest (3 tests)
**Status**: ⏸️ SKIPPED
**Reason**: ModelNotFoundException + TypeError
**Location**: `tests/Feature/Workflows/FeatureFlagIntegrationTest.php`

**Issues**:
- DocumentJob not found - missing tenant context when querying
- Workflow class type mismatch

**Fix**:
1. Wrap DocumentJob queries in `TenantContext::run()`
2. Fix workflow listener tenant context initialization
3. Correct workflow class argument types

---

### 11. ProductionInitializationTest (2 tests)
**Status**: ⏸️ SKIPPED  
**Reason**: QueryException - tenant database setup issues
**Location**: `tests/Feature/DeadDrop/ProductionInitializationTest.php`

**Fix**:
1. Ensure tenant migrations run successfully in test
2. Fix tenant context initialization sequence

---

### 12. ProductionWorkflowTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Same as #11
**Location**: `tests/Feature/DeadDrop/ProductionWorkflowTest.php`

**Fix**: Same as #11

---

### 13. TenantSchemaGuardTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: PDOException - database connection issues
**Location**: `tests/Feature/DeadDrop/TenantSchemaGuardTest.php`

**Fix**:
1. Fix tenant database creation in test environment
2. Ensure schema guard properly handles missing schemas

---

### 14. Phase12IntegrationTest (4 tests)
**Status**: ⏸️ SKIPPED
**Reason**: Multiple tenant context issues
**Location**: `tests/Feature/Phase12IntegrationTest.php`

**Fix**:
1. Update tests to use `UsesDashboardSetup` trait
2. Ensure tenant databases are properly created

---

### 15. SetupVerificationTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: CLI command test - environment issues
**Location**: `tests/Feature/SetupVerificationTest.php`

**Fix**:
1. Mock Artisan commands or test in isolation
2. Fix setup command to work in test environment

---

### 16. SetupTestCommandTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Seeder-related failures
**Location**: `tests/Feature/Commands/SetupTestCommandTest.php`

**Fix**:
1. Fix seeder execution in test environment
2. Ensure all 8 seeders run successfully

---

### 17. CleanupOldKycMediaTest (4 tests)
**Status**: ⏸️ SKIPPED
**Reason**: ErrorException - missing Contact model setup
**Location**: `tests/Feature/Console/CleanupOldKycMediaTest.php`

**Fix**:
1. Add proper Contact model factory usage
2. Fix media library integration in tests

---

### 18. TenancyServiceTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Tenant schema verification test failures
**Location**: `tests/Unit/Services/Tenancy/TenancyServiceTest.php`

**Fix**:
1. Update TenancyService schema verification logic
2. Align with current tenant migration structure

---

### 19. ExtractionProcessorTest (1 test)
**Status**: ⏸️ SKIPPED
**Reason**: Integration test requiring AI mocking
**Location**: `tests/Integration/Processors/ExtractionProcessorTest.php`

**Fix**:
1. Mock AI processor responses
2. Add test fixtures for extraction scenarios

---

## Quick Wins (Easiest to Fix)
1. **ContactModelTest** - Just add BelongsToTenant trait (#6)
2. **CsvImportLocalizedValidationTest** - Fix tenant_id in factory (#2)
3. **CampaignAccessWithoutSQLSTATETest** - Create Inertia component (#7)

## Medium Difficulty  
4. **TenancyTest** - Fix tenant DB creation in tests (#9)
5. **DocumentUploadRouteTest** - Implement controller + fix ULID collision (#1)
6. **CsvImportWithRegexTest** - Complete regex transformations (#3)

## Complex (Requires Significant Work)
7. **PipelineEndToEndTest** - Mock AI processors (#4)
8. **EKycVerificationProcessorTest** - Implement processor class (#5)
9. **FeatureFlagIntegrationTest** - Fix workflow tenant context (#10)

## Test Suite Goals
- [ ] Fix all Quick Wins (target: 3 days)
- [ ] Fix all Medium Difficulty (target: 1 week)
- [ ] Address Complex tests (target: 2 weeks)
- [ ] Achieve 100% test pass rate

## Notes
- Tests are skipped with `test()->markTestSkipped()` or `$this->markTestSkipped()`
- Skipped tests still appear in test output but don't count as failures
- Run specific test: `php artisan test --filter TestName`
- Run test file: `php artisan test path/to/TestFile.php`
