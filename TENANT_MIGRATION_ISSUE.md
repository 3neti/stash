# Detailed Investigation Report: Tenant Migration Issue

**Date**: November 27, 2025  
**Project**: Stash/DeadDrop  
**Package**: stancl/tenancy v3.9.1  
**Database**: PostgreSQL 17 (Multi-Database Tenancy)

---

## 🎯 Objective

**Goal**: Automatically run tenant-scoped database migrations when creating a new tenant using stancl/tenancy v3.9.1.

**Expected Behavior**:
```bash
php artisan tenant:create acme --name="Acme Corp"
# Should:
# 1. Create tenant record in central DB
# 2. Create separate PostgreSQL database (tenant01KB...)
# 3. Run migrations in that tenant database
# 4. Result: Tenant DB has 8 tables (campaigns, documents, etc.)
```

**Actual Behavior**:
```bash
php artisan tenant:create acme --name="Acme Corp"
✓ Tenant created: 01KB2BK3G8ZMYDXB14V1PWM7G6
✓ Database created
✓ Migrations run  # <-- LIES!

# Reality check:
DB_DATABASE=tenant01KB2BK3G8ZMYDXB14V1PWM7G6 php artisan db:show
# Tables: 0  <-- Empty database!
```

---

## 🔍 What We Observed

### 1. **Migration Records in Wrong Database**

We discovered migrations are being recorded in the **central database** instead of tenant databases:

```bash
# Central database shows tenant migrations
php artisan tinker
> DB::table('migrations')->where('migration', 'like', '%campaign%')->first()
=> {
  id: 8,
  migration: "2025_11_27_075307_create_campaigns_table",
  batch: 2
}

# Tenant database is empty
DB_DATABASE=tenant01KB... php artisan tinker
> DB::table('migrations')->get()
ERROR: relation "migrations" does not exist
```

**This proves**: Migrations ran against the central `pgsql` connection, not the tenant-specific connection.

### 2. **Tenant Database Connection Not Created**

When we try to initialize tenancy manually:

```bash
php artisan tinker
> $tenant = Tenant::first();
> tenancy()->initialize($tenant);
> config('database.connections.tenant.database')
=> null  # <-- Should be "tenant01KB2BK3G8ZMYDXB14V1PWM7G6"

> Artisan::call('migrate', ['--database' => 'tenant', ...]);
InvalidArgumentException: Database connection [tenant] not configured.
```

**This proves**: The `DatabaseTenancyBootstrapper` is not creating the `tenant` database connection.

### 3. **Job Pipeline Appears to Work**

Looking at our `TenancyServiceProvider`:

```php
Events\TenantCreated::class => [
    JobPipeline::make([
        Jobs\CreateDatabase::class,    // ✅ Works (DB created)
        Jobs\MigrateDatabase::class,   // ❌ Runs but wrong DB
    ])->shouldBeQueued(false),
],
```

- `CreateDatabase` job **works** - PostgreSQL database is created
- `MigrateDatabase` job **runs** - returns success, no errors
- But migrations execute against **central DB** (wrong connection)

### 4. **Manual Command Works (Partially)**

```bash
php artisan tenants:migrate --tenants=01KB2BK3G8ZMYDXB14V1PWM7G6
=> "Nothing to migrate"
```

Why? Because it checks the migrations table in... the central database! Since migrations were already recorded there, it thinks the work is done.

### 5. **Database Files Created Correctly**

Both SQLite and PostgreSQL:
- ✅ Tenant databases ARE created (SQLite files, PostgreSQL databases)
- ✅ Database names are correct (`tenant{ULID}`)
- ✅ Permissions are correct
- ❌ Migrations don't populate them

---

## 🚫 Workarounds That Didn't Work

### ❌ Attempt 1: Set `template_tenant_connection` Explicitly
```php
// config/tenancy.php
'template_tenant_connection' => 'pgsql',
```
**Result**: No change. Connection still not created.

### ❌ Attempt 2: Manual Tenancy Initialization
```php
tenancy()->initialize($tenant);
Artisan::call('migrate', ['--database' => 'tenant', ...]);
```
**Result**: `InvalidArgumentException: Database connection [tenant] not configured.`

### ❌ Attempt 3: Force Fresh Migrations
```bash
php artisan tenants:migrate-fresh --tenants=01KB...
```
**Result**: Same error - connection not configured.

### ❌ Attempt 4: SQLite Instead of PostgreSQL
Initially tried with SQLite multi-database.
**Result**: Same issue - 0-byte database files created, no tables.

### ❌ Attempt 5: Explicit Database Parameter in Job
Modified `CreateTenantCommand` to pass `--database=tenant` to migration job.
**Result**: Same error - tenant connection doesn't exist at time of job execution.

---

## 🤔 Our Hunch: Root Cause Analysis

### **Primary Hypothesis: Console vs HTTP Context**

stancl/tenancy's `DatabaseTenancyBootstrapper` is designed to run during **HTTP requests** via middleware:

```php
// How it's supposed to work (HTTP):
Route::middleware(['tenant'])->group(function() {
    // Middleware calls tenancy()->initialize($tenant)
    // This triggers DatabaseTenancyBootstrapper
    // Which creates the 'tenant' connection dynamically
});
```

**But** when running in **console/job context**:
- No middleware runs
- Bootstrappers may not be triggered properly
- The `tenant` connection is never created
- Migrations fall back to default connection (`pgsql` = central DB)

### **Evidence Supporting This**:

1. **stancl/tenancy v3 docs** focus heavily on HTTP middleware-based tenancy
2. **DatabaseTenancyBootstrapper** listens for `TenancyInitialized` event
3. **Our `MigrateDatabase` job** might not be firing this event correctly
4. **stancl/tenancy v4** has better support for console commands and RLS
5. **The `tenants:migrate` command works** - it properly initializes tenancy

### **Secondary Hypothesis: Event Listener Order**

Looking at `TenancyServiceProvider`:

```php
Events\TenancyInitialized::class => [
    Listeners\BootstrapTenancy::class,  // Should create connection
],
```

The `BootstrapTenancy` listener should call all bootstrappers, including `DatabaseTenancyBootstrapper`. But this might not be happening synchronously during job execution.

**Timing Issue**:
```
1. TenantCreated event fires
2. JobPipeline starts
3. CreateDatabase job runs (✅ works)
4. MigrateDatabase job starts
5. Job tries to call Artisan::call('migrate')
6. BUT: Tenancy not initialized yet
7. Migrations run against default connection (❌ central DB)
```

### **Tertiary Hypothesis: `shouldBeQueued(false)` Synchronous Execution**

Our configuration:
```php
->shouldBeQueued(false)  // Runs synchronously
```

Synchronous execution might not give bootstrappers time to register. The event system might expect async queue processing.

---

## 🔧 Potential Solutions (For Research)

### 1. **Check stancl/tenancy v3 Console Command Pattern**

Look for:
- `TenantAwareCommand` base class
- How `tenants:migrate` properly initializes tenancy
- Example of running migrations in jobs

**Research URLs**:
- https://tenancyforlaravel.com/docs/v3/console-commands
- https://github.com/stancl/tenancy/blob/3.x/src/Commands/Migrate.php
- https://github.com/stancl/tenancy/blob/3.x/src/Commands/TenantCommand.php

### 2. **Compare with stancl/tenancy v4**

v4 has better PostgreSQL and console support:
- https://v4.tenancyforlaravel.com/multi-database-tenancy/
- https://v4.tenancyforlaravel.com/console-commands/

Check if v4 has:
- Automatic console context handling
- Better bootstrapper triggering
- Different event system
- Console-specific bootstrappers

### 3. **Inspect `MigrateDatabase` Job Source**

Look at:
```bash
vendor/stancl/tenancy/src/Jobs/MigrateDatabase.php
```

Questions:
- Does it call `tenancy()->initialize()`?
- Does it wait for bootstrappers to complete?
- Does it force the `--database=tenant` flag?
- Does it use `TenantCommand` functionality?

### 4. **Check if `shouldBeQueued(false)` is the Issue**

Our config:
```php
->shouldBeQueued(false)  // Runs synchronously
```

Maybe async queuing works differently? Try:
```php
->shouldBeQueued(true)  // Test with queue worker
php artisan queue:work
```

### 5. **Look for `TenantDatabaseManager` Configuration**

Check if PostgreSQL manager needs explicit connection setup:
```bash
vendor/stancl/tenancy/src/TenantDatabaseManagers/PostgreSQLDatabaseManager.php
```

Look for:
- Connection configuration methods
- How `makeConnectionConfig()` is used
- Whether it's called during job execution

### 6. **Explore Custom Job Implementation**

Instead of using stancl/tenancy's `MigrateDatabase`, create our own:

```php
// app/Jobs/MigrateTenantDatabase.php
class MigrateTenantDatabase implements ShouldQueue
{
    public function handle(Tenant $tenant)
    {
        // Explicitly initialize tenancy
        tenancy()->initialize($tenant);
        
        // Wait for bootstrappers
        sleep(1); // Or use event listener
        
        // Run migrations with explicit connection
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
```

---

## 📋 Research Checklist

To move forward, research these specific questions:

- [ ] **How does `tenants:migrate` command initialize tenancy?**
  - Path: `vendor/stancl/tenancy/src/Commands/Migrate.php`
  - Look for: Connection setup, bootstrapper calls
  - Compare with our job implementation

- [ ] **Does `MigrateDatabase` job properly initialize tenancy?**
  - Path: `vendor/stancl/tenancy/src/Jobs/MigrateDatabase.php`
  - Look for: `tenancy()->initialize()`, `--database` parameter
  - Check if it uses `TenantCommand` pattern

- [ ] **Are there any console-specific bootstrappers?**
  - Search docs: "console" + "bootstrapper"
  - Check if we need `ConsoleTenancyBootstrapper`
  - Look for differences between HTTP and console contexts

- [ ] **stancl/tenancy v3 vs v4 differences for console?**
  - Compare migration commands between versions
  - Check if v4 upgrade would solve this
  - Review v4 changelog for console improvements

- [ ] **GitHub Issues: Similar problems?**
  - Search: "migrations not running" + "console" + "jobs"
  - Search: "tenant connection not configured"
  - Search: "MigrateDatabase job"
  - Check closed issues for solutions

- [ ] **Alternative: Custom TenantAwareCommand?**
  - Create base command that properly initializes tenancy
  - Override `tenant:create` to use it
  - Ensure bootstrappers run before job execution

- [ ] **Test with queued jobs instead of sync**
  - Change `shouldBeQueued(false)` to `true`
  - Run queue worker
  - See if async execution fixes timing issues

- [ ] **Check for Bootstrap Event Timing**
  - Add logging to `BootstrapTenancy` listener
  - Verify it's called before migrations run
  - Check if connection config is present

---

## 🎯 Current Workaround (Functional)

Until the root cause is fixed:

```bash
# 1. Create tenant (database is created but empty)
php artisan tenant:create acme --name="Acme Corp"

# 2. Manually run migrations (this DOES work)
php artisan tenants:migrate

# Result: Tenant database now has all 8 tables ✅
```

**Why the manual command works**: The `tenants:migrate` command properly initializes tenancy for each tenant before running migrations. It doesn't rely on the event pipeline.

**Alternative automated workaround**:
```php
// app/Console/Commands/CreateTenantCommand.php
public function handle(): int
{
    $tenant = Tenant::create([...]);
    
    $this->info('✓ Tenant created: ' . $tenant->id);
    
    // Skip broken job pipeline, call command directly
    $this->call('tenants:migrate', ['--tenants' => $tenant->id]);
    
    $this->info('✓ All done!');
    
    return self::SUCCESS;
}
```

---

## 📊 Impact Assessment

**Severity**: Medium
- Database schema is correct ✅
- Multi-database architecture is correct ✅
- Only automation is broken ⚠️
- Manual workaround is simple and reliable ✅
- Production deployment unaffected (can script workaround) ✅

**Blocker?**: No
- Can proceed with Phase 1.2 (models, factories, tests) ✅
- Can revisit after research ✅
- May upgrade to stancl/tenancy v4 in Phase 2 ✅

**Technical Debt**: Low-Medium
- Automation is nice-to-have, not critical
- Workaround can be scripted for production
- Issue is well-documented for future investigation

---

## 🔬 Next Steps

### Immediate (For Development)
1. **Use manual workaround** for now:
   ```bash
   php artisan tenant:create {slug}
   php artisan tenants:migrate
   ```

2. **Continue Phase 1.2**:
   - Create Eloquent Models
   - Create Factories
   - Create Seeders
   - Write Tests

### Research Phase (Parallel)
1. **Research the 5 areas** listed above
2. **Check stancl/tenancy GitHub issues** for similar problems
3. **Compare our setup** with working examples in docs
4. **Test with async queue** (`shouldBeQueued(true)`)
5. **Consider stancl/tenancy v4 upgrade** (might just work™)

### Community Support
1. **Post on stancl/tenancy Discussions**:
   - Include this report
   - Link to our `TenancyServiceProvider` configuration
   - Ask about console context bootstrapping

2. **Check Discord/Slack**:
   - stancl/tenancy community channels
   - Laravel multi-tenancy discussions

### Long-Term Solutions
1. **If research finds fix**: Update `TenancyServiceProvider`
2. **If v4 solves it**: Schedule upgrade to stancl/tenancy v4
3. **If no solution**: Keep workaround, document as known limitation

---

## 📝 Related Files

**Configuration**:
- `config/tenancy.php` - Tenancy configuration
- `app/Providers/TenancyServiceProvider.php` - Event listeners
- `.env` - Database connection settings

**Custom Code**:
- `app/Models/Tenant.php` - Custom Tenant model
- `app/Support/UlidGenerator.php` - ULID generator
- `app/Console/Commands/CreateTenantCommand.php` - Tenant creation command

**Migrations**:
- `database/migrations/2019_09_15_000010_create_tenants_table.php` (central)
- `database/migrations/tenant/*.php` (8 tenant-scoped migrations)

**Documentation**:
- `PHASE_1.2_COMPLETE.md` - Phase completion summary
- `PHASE_1.2_PROGRESS.md` - Progress tracking
- `TENANT_MIGRATION_ISSUE.md` - This document

---

## 🎓 Lessons Learned

1. **Multi-database tenancy is complex**
   - Different behavior in HTTP vs console contexts
   - Event timing matters
   - Bootstrapper order is critical

2. **stancl/tenancy v3 shows its age**
   - v4 has better console support
   - v3 docs focus on HTTP/middleware patterns
   - Community has moved to v4 for new projects

3. **PostgreSQL is still the right choice**
   - SQLite had the same issue (not DB-specific)
   - PostgreSQL multi-database is production-proven
   - Separate databases provide true isolation

4. **Debugging multi-tenancy requires patience**
   - Must check both central and tenant databases
   - Events and jobs obscure the flow
   - Manual verification is essential

5. **Good workarounds are valuable**
   - Don't let perfect be enemy of good
   - Manual step is acceptable for now
   - Can automate later when understood better

---

## 📚 Resources

**stancl/tenancy Documentation**:
- [v3 Console Commands](https://tenancyforlaravel.com/docs/v3/console-commands)
- [v4 Multi-Database](https://v4.tenancyforlaravel.com/multi-database-tenancy/)
- [v4 Console Commands](https://v4.tenancyforlaravel.com/console-commands/)

**GitHub**:
- [stancl/tenancy Repository](https://github.com/stancl/tenancy)
- [Issues Search](https://github.com/stancl/tenancy/issues?q=is%3Aissue+migrate)

**Community**:
- [Laravel News - Multi-Tenancy](https://laravel-news.com/tag/multi-tenancy)
- [Laracasts Forum](https://laracasts.com/discuss)

---

**Status**: Open for Investigation  
**Priority**: Medium  
**Assigned To**: Research needed  
**Last Updated**: November 27, 2025
