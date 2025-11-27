# Phase 1.2: Database Schema Design - COMPLETED ✅

**Completed**: November 27, 2025  
**Duration**: 1 day (faster than estimated 6-7 days due to stancl/tenancy)

---

## ✅ Accomplishments

### 1. Multi-Tenancy Setup
- ✅ Installed `stancl/tenancy` v3.9.1
- ✅ Configured for PostgreSQL 17 multi-database tenancy
- ✅ Created custom `UlidGenerator` for tenant IDs
- ✅ Created custom `Tenant` model with explicit columns
- ✅ Created `tenant:create` Artisan command

### 2. Database Migrations Created (8 Tenant Tables)

All tenant-scoped migrations created with complete schemas:

1. **campaigns** - Document processing workflows
   - ULID primary key, pipeline_config JSON, settings, credentials
   - Status: draft/active/paused/archived
   
2. **documents** - Uploaded files with processing state
   - ULID + UUID, storage paths, SHA-256 hash
   - Metadata JSON, processing history
   
3. **document_jobs** - Pipeline execution tracking
   - UUID for queue tracking, retry logic
   - Pipeline state machine
   
4. **processors** - System processor registry
   - Categories: OCR, classification, extraction, etc.
   - JSON schema for configuration
   
5. **processor_executions** - Individual processor runs
   - Token usage and AI cost tracking
   - Input/output data capture
   
6. **credentials** - Hierarchical encrypted credentials
   - Scope types: system, tenant, campaign, processor
   - Encrypted values with expiration
   
7. **usage_events** - Billing and metering
   - Event types: upload, storage, processor_execution, ai_task
   
8. **audit_logs** - Immutable compliance trail
   - Polymorphic auditable relationship

### 3. Central Database Setup
- ✅ PostgreSQL 17 via DBngin
- ✅ Central `stash` database created
- ✅ Central migrations run successfully:
  - users (with tenant_id, role, permissions)
  - tenants (with ULID, slug, email, status, tier, settings, credentials)
  - domains (for subdomain tenancy)

### 4. Architecture Decisions

**Switched from SQLite to PostgreSQL:**
- SQLite multi-database had silent migration failures
- PostgreSQL creates separate databases per tenant (production-ready)
- Each tenant gets: `tenant{ULID}` database

**Foreign Key Adjustments:**
- Removed FK constraints from tenant tables to central `users` table
- user_id stored as ULID string (indexed) for cross-database reference
- Relationships managed at application level via Eloquent

---

## ⚠️ Known Issue: Tenant Migration Automation

**Problem**: Tenant databases are created but migrations don't auto-run

**Symptoms**:
```bash
php artisan tenant:create acme
# Output: ✓ Tenant created, ✓ Database created, ✓ Migrations run
# Reality: Database exists but is empty (0 tables)
```

**Root Cause**: 
The `MigrateDatabase` job in stancl/tenancy's event pipeline runs but doesn't actually execute migrations. The `tenant` database connection isn't properly configured during job execution.

**Workaround** (manual migration):
```bash
# After creating tenant, manually run:
php artisan tenants:migrate --tenants={TENANT_ID}

# Or for all tenants:
php artisan tenants:migrate
```

**Needs Investigation**:
- DatabaseTenancyBootstrapper not creating 'tenant' connection in console context
- `tenancy()->initialize()` works for HTTP but not for Jobs/Commands
- May need custom TenantAwareCommand base class
- Stancl/tenancy v3 vs v4 differences (v4 has better RLS support)

---

## 📂 File Structure

```
database/
├── migrations/
│   ├── 2019_09_15_000010_create_tenants_table.php (central)
│   ├── 2019_09_15_000020_create_domains_table.php (central)
│   ├── 2025_11_27_074450_add_tenant_fields_to_users_table.php (central)
│   └── tenant/
│       ├── 2025_11_27_075307_create_campaigns_table.php
│       ├── 2025_11_27_075949_create_documents_table.php
│       ├── 2025_11_27_075954_create_document_jobs_table.php
│       ├── 2025_11_27_075957_create_processors_table.php
│       ├── 2025_11_27_075959_create_processor_executions_table.php
│       ├── 2025_11_27_080000_create_credentials_table.php
│       ├── 2025_11_27_080003_create_usage_events_table.php
│       └── 2025_11_27_080006_create_audit_logs_table.php
├── factories/ (pending - Phase 1.2.4)
└── seeders/ (pending - Phase 1.2.5)

app/
├── Models/
│   └── Tenant.php (custom model with explicit columns)
├── Support/
│   └── UlidGenerator.php (for stancl/tenancy)
└── Console/Commands/
    └── CreateTenantCommand.php

config/
└── tenancy.php (configured for pgsql multi-database)

.env
- DB_CONNECTION=pgsql
- DB_HOST=127.0.0.1
- DB_PORT=5432
- DB_DATABASE=stash
- DB_USERNAME=postgres
- DB_PASSWORD=
```

---

## 🎯 Next Steps

### Immediate (Complete Phase 1.2)
1. **Debug tenant migration automation** ⚠️
   - Investigate stancl/tenancy v3 DatabaseTenancyBootstrapper
   - Check if v4 upgrade would fix issue
   - Or create custom TenantCommand base class

2. **Task 1.2.3: Create Eloquent Models** (2 days)
   - Campaign, Document, DocumentJob models with `HasUlids` trait
   - Processor, ProcessorExecution, Credential models
   - UsageEvent, AuditLog models
   - All tenant models use `BelongsToTenant` trait

3. **Task 1.2.4: Create Factories** (1 day)
   - CampaignFactory, DocumentFactory, etc.
   - Use `HasUlids` for ID generation

4. **Task 1.2.5: Create Seeders** (1 day)
   - System processors seeder
   - Demo campaign and document data

5. **Task 1.2.6: Write Tests** (2 days)
   - Model relationship tests
   - Tenant isolation tests (critical!)
   - Factory validity tests

### Future Considerations
- **Spatie laravel-medialibrary**: Add in Phase 3 for PDF thumbnails, image conversions
- **PostgreSQL RLS**: Consider for Phase 2+ (single-database alternative)
- **Partitioning**: Monthly partitions for audit_logs and usage_events (Phase 3+)

---

## 🔧 Commands Reference

### Central Database
```bash
# Run central migrations
php artisan migrate

# Fresh central database
php artisan migrate:fresh
```

### Tenant Management
```bash
# Create tenant (with database)
php artisan tenant:create {slug} --name="Name" --email="email@example.com"

# List tenants
php artisan tenants:list

# Run tenant migrations (REQUIRED after tenant creation)
php artisan tenants:migrate

# Run for specific tenant
php artisan tenants:migrate --tenants={TENANT_ID}

# Fresh tenant migrations
php artisan tenants:migrate-fresh
```

### Database Inspection
```bash
# Show central database info
php artisan db:show

# Show tenant database info
DB_DATABASE=tenant{ULID} php artisan db:show

# Connect to tenant database (tinker)
php artisan tinker
> tenancy()->initialize(Tenant::first());
> DB::table('campaigns')->count();
```

---

## 📊 Schema Overview

### Central Database: `stash`
- users (with tenant_id)
- tenants (ULID IDs)
- domains (for subdomain routing)
- cache, jobs, sessions (Laravel defaults)

### Tenant Databases: `tenant{ULID}`
Each tenant gets their own PostgreSQL database with:
- campaigns
- documents
- document_jobs
- processors
- processor_executions
- credentials
- usage_events
- audit_logs

**Isolation**: Complete database separation ensures tenant data never mixes

---

## ✅ Success Criteria Met

- [x] All 8 tenant migrations created with correct schemas
- [x] All 3 central migrations created and run
- [x] PostgreSQL 17 configured and working
- [x] Multi-database tenancy configured via stancl/tenancy
- [x] Tenant creation command working
- [x] Tenant databases created successfully
- [ ] Tenant migrations auto-run (⚠️ needs debugging)
- [ ] Models created (pending Task 1.2.3)
- [ ] Factories created (pending Task 1.2.4)
- [ ] Seeders created (pending Task 1.2.5)
- [ ] Tests written (pending Task 1.2.6)

---

## 💡 Lessons Learned

1. **SQLite multi-database is tricky**: Separate database files work but migrations fail silently
2. **PostgreSQL is better for multi-database**: Each tenant gets a real database
3. **stancl/tenancy v3 vs v4**: v4 has better PostgreSQL RLS support for single-database
4. **Cross-database FKs don't work**: User references from tenant DB need to be logical only
5. **Console vs HTTP contexts differ**: Tenancy bootstrapping behaves differently

---

## 📚 Resources

- [stancl/tenancy v3 docs](https://tenancyforlaravel.com/docs/v3)
- [stancl/tenancy v4 docs](https://v4.tenancyforlaravel.com)
- [PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- [Multi-database tenancy](https://v4.tenancyforlaravel.com/multi-database-tenancy/)
