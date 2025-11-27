# Phase 1.2: Database Schema Design - Progress

## Status: In Progress ⏳ - Migrations Complete, Debugging Multi-Database Setup

**Started**: November 27, 2025  
**Current**: All 8 tenant migrations created, stancl/tenancy configured
**Estimated Completion**: 6-7 days

---

## Key Decision: Using stancl/tenancy Package

### ✅ Installed & Configured
- **Package**: `stancl/tenancy` v3.9.1
- **ID Generator**: ULIDGenerator (for tenant IDs)
- **Central Domains**: `127.0.0.1`, `localhost`, `stash.test`
- **Filesystem**: Tenant-aware for `local`, `public`, `s3`

### Benefits
- ✅ **Time Saved**: 2-3 days of development
- ✅ **Security**: Battle-tested protection against cross-tenant leaks
- ✅ **Auto-Scoping**: Automatic tenant filtering on queries
- ✅ **Tenant-Aware**: Filesystem, cache, and queues automatically scoped
- ✅ **ULID Support**: Built-in ULID generator

---

## Completed Tasks

### ✅ Task 1.2.0: Package Installation & Configuration (0.5 days)
- [x] Installed `stancl/tenancy` via Composer
- [x] Ran `php artisan tenancy:install`
- [x] Configured `config/tenancy.php`:
  - Set `id_generator` to `ULIDGenerator`
  - Added `stash.test` to central domains
  - Enabled S3 disk for tenant-aware storage
- [x] Customized `tenants` table migration with Stash fields
- [x] Created `add_tenant_fields_to_users_table` migration
- [x] Updated Phase 1.2 plan to reflect stancl/tenancy integration

---

## Current Migrations

### Central Database (Non-Tenant Data)
1. ✅ `2019_09_15_000010_create_tenants_table.php`
   - Customized with Stash-specific fields
   - Uses ULID primary key
   - Includes: name, slug, email, status, tier, settings, credentials, credit_balance

2. ✅ `2019_09_15_000020_create_domains_table.php`
   - Standard stancl/tenancy domains table
   - For subdomain-based tenant identification

3. ✅ `2025_11_27_074450_add_tenant_fields_to_users_table.php`
   - Adds `tenant_id` foreign key
   - Adds `role` (owner, admin, member, viewer)
   - Adds `permissions` JSON field

### Tenant Database (Tenant-Specific Data)
*These migrations are in `database/migrations/tenant/` directory*

1. ✅ `2025_11_27_075307_create_campaigns_table.php`
   - ULID primary key
   - Pipeline configuration (JSON)
   - Campaign settings and credentials
   - Status: draft, active, paused, archived

2. ✅ `2025_11_27_075949_create_documents_table.php`
   - ULID primary key + UUID for public access
   - Storage path and disk (S3 support)
   - SHA-256 hash for integrity
   - Processing state and metadata

3. ✅ `2025_11_27_075954_create_document_jobs_table.php`
   - ULID primary key + UUID for queue tracking
   - Pipeline execution state
   - Retry logic with max_attempts

4. ✅ `2025_11_27_075957_create_processors_table.php`
   - System processor registry
   - Configuration JSON schema
   - Categories: ocr, classification, extraction, etc.

5. ✅ `2025_11_27_075959_create_processor_executions_table.php`
   - Individual processor runs
   - Token usage and cost tracking
   - Input/output data capture

6. ✅ `2025_11_27_080000_create_credentials_table.php`
   - Hierarchical credential storage
   - Encrypted values
   - Scope types: system, tenant, campaign, processor

7. ✅ `2025_11_27_080003_create_usage_events_table.php`
   - Billing and metering
   - Event types: upload, storage, processor_execution, ai_task

8. ✅ `2025_11_27_080006_create_audit_logs_table.php`
   - Immutable compliance trail
   - Polymorphic auditable relationship

---

## Next Steps

### Task 1.2.1: Create Tenant Migrations (1.5 days remaining)
Need to create migrations in `database/migrations/tenant/`:

1. **Campaigns Table**
   - Document processing workflows
   - Pipeline configuration (JSON)
   - Tenant-scoped (automatic via stancl/tenancy)

2. **Documents Table**
   - Uploaded files
   - Processing state
   - Storage paths (tenant-aware filesystem)

3. **Document Jobs Table**
   - Pipeline execution instances
   - Queue job tracking

4. **Processors Table**
   - System processor registry
   - Configuration schemas

5. **Processor Executions Table**
   - Individual processor runs
   - Token usage tracking

6. **Credentials Table**
   - Hierarchical credential storage
   - Encrypted values

7. **Usage Events Table**
   - Billing and metering
   - Token consumption

8. **Audit Logs Table**
   - Immutable compliance trail
   - All tenant actions

---

## Architecture Notes

### Multi-Tenancy Model
**Single Database with Row-Level Isolation**

- **Central Tables**: `tenants`, `domains`, `users` (with `tenant_id`)
- **Tenant Tables**: All other tables (automatically scoped by stancl/tenancy)
- **Column Naming**: Using `tenant_id` (stancl/tenancy convention) instead of `subscriber_id`
- **Tenant Resolution**: Via middleware, API keys, subdomains

### How stancl/tenancy Works
1. Request hits middleware
2. Tenant identified (subdomain, API key, etc.)
3. Tenancy initialized (bootstrappers run)
4. All queries automatically scoped to tenant
5. Filesystem, cache, queues become tenant-aware

### Tenant vs Central Data

**Central (Shared)**:
- Tenant records (subscribers)
- Domain mappings
- User accounts (with tenant_id)
- System configuration

**Tenant (Isolated)**:
- Campaigns
- Documents
- Jobs
- Processors
- Credentials
- Usage events
- Audit logs

---

## Configuration Files

### `config/tenancy.php`
Key settings:
```php
'tenant_model' => Tenant::class,
'id_generator' => Stancl\Tenancy\ULIDGenerator::class,
'central_domains' => ['127.0.0.1', 'localhost', 'stash.test'],
'bootstrappers' => [
    DatabaseTenancyBootstrapper::class,
    CacheTenancyBootstrapper::class,
    FilesystemTenancyBootstrapper::class,
    QueueTenancyBootstrapper::class,
],
```

### Routes
- **Central Routes**: `routes/web.php`, `routes/api.php`
- **Tenant Routes**: `routes/tenant.php` (automatically scoped)

---

## Testing Strategy

### With stancl/tenancy
- Use `tenancy()->initialize($tenant)` in tests
- Test cross-tenant isolation automatically
- Tenant database migrations run via `php artisan tenants:migrate`

### Example Test Pattern
```php
test('campaigns are tenant-isolated', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create();
    
    tenancy()->initialize($tenant2);
    $campaign2 = Campaign::factory()->create();
    
    expect(Campaign::count())->toBe(1); // Only sees tenant2's campaign
});
```

---

## Database Commands

### Run Central Migrations
```bash
php artisan migrate
```

### Run Tenant Migrations
```bash
php artisan tenants:migrate
```

### Seed Tenants
```bash
php artisan tenants:seed
```

### Create Test Tenant
```bash
php artisan tenants:create
```

---

## Success Metrics

**Phase 1.2 Complete When**:
- ✅ stancl/tenancy installed and configured
- ⏳ All 8 tenant migrations created
- ⏳ All migrations run successfully (central + tenant)
- ⏳ All models created with `BelongsToTenant` trait
- ⏳ All factories produce valid data
- ⏳ Seeders create demo tenants and data
- ⏳ Tests verify tenant isolation
- ⏳ 100% test coverage on models

---

## Resources

- [stancl/tenancy Documentation](https://tenancyforlaravel.com/docs/v3)
- [ULID Specification](https://github.com/ulid/spec)
- Phase 1.2 Plan: See Plan ID `5dcf95d1-02cd-41a1-a331-06720b6e673b`
