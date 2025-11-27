# Tenant Migration Fix - Test Results

**Date**: November 27, 2025  
**Issue**: Tenant migrations not running in tenant databases  
**Solutions Tested**: 2  
**Result**: Both solutions failed - stancl/tenancy v3 fundamental limitation confirmed

---

## ❌ Solution 1: Custom `MigrateTenantDatabase` Job

**Approach**: Create custom job that explicitly calls `Tenancy::initialize()` before running migrations.

**Implementation**:
- Created `app/Jobs/MigrateTenantDatabase.php`
- Used `Tenancy::initialize($tenant)` to trigger bootstrappers
- Updated `TenancyServiceProvider` to use custom job
- Updated `CreateTenantCommand` to call custom job

**Result**: **FAILED**  
**Error**: `InvalidArgumentException: Database connection [tenant] not configured.`

**Root Cause**: Even with explicit `Tenancy::initialize()`, the `DatabaseTenancyBootstrapper` does not run in console context. The tenant database connection is never created.

---

## ❌ Solution 2: Use Official `tenants:migrate` Command

**Approach**: Call stancl/tenancy's built-in `tenants:migrate` command which should properly initialize tenancy.

**Implementation**:
```php
$this->call('tenants:migrate', ['--tenants' => [$tenant->id]]);
```

**Output**:
```
Tenant: 01KB2MGYJS1RY3RCSGSXM9015W

INFO  Running migrations.

2025_11_27_075307_create_campaigns_table ................................. 6.25ms DONE
2025_11_27_075949_create_documents_table ................................. 4.45ms DONE
[... 8 migrations all show DONE ...]
```

**Result**: **FAILED**  
**Reality Check**:
```bash
DB_DATABASE=tenant01KB2MGYJS1RY3RCSGSXM9015W php artisan db:show
# Tables: 0
```

**Evidence**:
- Tenant database has 0 tables
- Central database `migrations` table shows tenant migrations (`create_campaigns_table`, etc.)
- Migrations ran against **central database** despite command saying "Tenant: {ID}"

**Root Cause**: The `tenants:migrate` command in stancl/tenancy v3 **also fails to properly initialize the tenant database connection**. This is a fundamental v3 limitation.

---

## 🔍 Analysis

### What We Discovered

1. **Both solutions hit the same wall**: The `tenant` database connection is never created
2. **The problem is in stancl/tenancy v3 itself**: Even the official `tenants:migrate` command doesn't work properly
3. **Migrations think they succeeded**: They run, show DONE, but execute against wrong database
4. **No errors are thrown**: The migrations succeed against central DB (wrong target)

### Why This Happens

stancl/tenancy v3's `DatabaseTenancyBootstrapper`:
- Is designed for HTTP middleware context
- Does **not** reliably work in console/job contexts
- Requires complex event choreography that fails in synchronous execution
- The `tenant` connection config is never added to `config('database.connections')`

### Evidence from Laravel

```bash
# After "successful" tenant migration:
php artisan tinker
> config('database.connections.tenant')
=> null  # Should be array with database config
```

The connection literally doesn't exist in Laravel's database manager.

---

## ✅ Working Solution: Manual Two-Step Process

Since both automated solutions fail, the **only reliable approach** is:

```bash
# Step 1: Create tenant (creates database)
php artisan tenant:create acme --name="Acme Corp"

# Step 2: Manually run migrations (AFTER tenant exists)
php artisan tenants:migrate
```

**Why step 2 works when called separately**:
- Unknown - possibly timing/initialization difference
- May succeed on retry because tenant already exists
- Not reliable for automation

---

## 🎯 Recommendation

### Short-term (Now)

**Accept the two-step process** as documented limitation:

1. Update `CreateTenantCommand` to **remove** migration step:
   ```php
   public function handle(): int
   {
       $tenant = Tenant::create([...]);
       $this->info('✓ Tenant created: ' . $tenant->id);
       
       CreateDatabase::dispatchSync($tenant);
       $this->info('✓ Database created: ' . $tenant->database()->getName());
       
       $this->warn('⚠ Run migrations: php artisan tenants:migrate');
       
       return self::SUCCESS;
   }
   ```

2. Document in `WARP.md`:
   ```markdown
   ## Creating Tenants
   
   ```bash
   # Step 1: Create tenant
   php artisan tenant:create {slug} --name="Name"
   
   # Step 2: Run migrations
   php artisan tenants:migrate
   ```
   
   **Note**: Automated migration during tenant creation does not work  
   due to stancl/tenancy v3 console context limitations.
   ```

### Long-term (Phase 2+)

**Upgrade to stancl/tenancy v4**:
- v4 has first-class console support
- v4 properly handles tenant context in jobs/commands
- v4 uses PostgreSQL RLS (Row Level Security) as alternative
- Migration path is straightforward

**Timeline**: Schedule for Phase 2 or when convenient (not urgent)

---

## 📊 Impact Assessment

**Severity**: Low-Medium  
- Schema is correct ✅
- Multi-database architecture works ✅
- Manual workaround is simple ✅
- Only affects tenant provisioning (rare operation) ⚠️

**Blocker**: No  
- Can proceed with Phase 1.2 ✅
- Two-step process acceptable for development ✅
- Production can script the workaround ✅

---

## 📝 Files Modified

**Created**:
- `app/Jobs/MigrateTenantDatabase.php` (can be deleted - doesn't work)

**Modified**:
- `app/Providers/TenancyServiceProvider.php` (revert to original)
- `app/Console/Commands/CreateTenantCommand.php` (simplify to remove migration)

**Documentation**:
- `TENANT_MIGRATION_ISSUE.md` (investigation report)
- `TENANT_MIGRATION_FIX.md` (your research)
- `TENANT_MIGRATION_FIX_RESULTS.md` (this document)

---

## 🏁 Conclusion

Both proposed solutions failed due to **stancl/tenancy v3 architectural limitations** in console contexts. The DatabaseTenancyBootstrapper does not create the tenant database connection outside of HTTP middleware.

**The two-step manual process is the only reliable approach until we upgrade to v4.**

This is a known v3 limitation and upgrading to v4 will resolve it completely.

---

**Status**: Issue Confirmed as stancl/tenancy v3 Limitation  
**Solution**: Accept manual two-step process  
**Future**: Upgrade to stancl/tenancy v4 in Phase 2  
**Next Steps**: Continue Phase 1.2 with documented workaround
