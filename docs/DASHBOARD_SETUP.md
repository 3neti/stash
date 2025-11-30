# Dashboard Setup for Testing

This guide covers setting up the Phase 3.1 Subscriber Dashboard for visual testing with Laravel Herd.

## Quick Start

```bash
php artisan dashboard:setup-test --no-interaction
```

This command will:
1. Create or reuse the "Test Company" tenant
2. Create the tenant database and run migrations
3. Seed 6 campaigns and 24 documents
4. Create/update the test user with proper tenant linkage

## Test Credentials

- **URL**: http://stash.test
- **Email**: test@example.com
- **Password**: password
- **Role**: owner

## What Gets Created

### Tenant Structure
- **Name**: Test Company
- **Slug**: test-company
- **Domain**: test.localhost
- **Database**: Separate tenant database with all migrations

### Test Data
- **6 Campaigns**:
  - 3 active custom campaigns
  - 2 paused custom campaigns
  - 1 active template campaign
- **24 Documents**: Generated via factory with realistic data

### User Linkage
The test user is created in the **central database** with:
- `tenant_id` linked to the tenant
- `role` set to 'owner' (not the default 'member')
- Email verified automatically

## Multi-Tenancy Architecture

This application uses **multi-database multi-tenancy**:

### Central Database (`pgsql` connection)
- `tenants` table
- `users` table (with `tenant_id` foreign key)
- Other shared resources

### Tenant Databases (`tenant` connection)
Each tenant has a separate database containing:
- `campaigns` table
- `documents` table
- `processors` table
- Other tenant-scoped resources

### How It Works

1. **Authentication**: User logs in via central database
2. **Middleware**: `InitializeTenantFromUser` middleware reads user's `tenant_id`
3. **Context**: `TenantContext::initialize()` sets up the tenant database connection
4. **Queries**: Models with `BelongsToTenant` trait automatically use the tenant connection

## Command Options

```bash
# Standard setup (reuses existing tenant/user if present)
php artisan dashboard:setup-test

# After migrate:fresh (command handles user linkage automatically)
php artisan migrate:fresh
php artisan dashboard:setup-test

# Fresh setup (drops all tables and recreates - requires confirmation)
php artisan dashboard:setup-test --fresh

# Custom tenant name and domain
php artisan dashboard:setup-test \
  --tenant-name="Demo Company" \
  --tenant-domain=demo.localhost
```

## Troubleshooting

### Issue: "relation 'campaigns' does not exist"

**Cause**: User has `tenant_id = NULL`, so middleware cannot initialize tenant context.

**Solution**: Run the setup command again - it will update the existing user:
```bash
php artisan dashboard:setup-test --no-interaction
```

The command now intelligently:
- Finds users by email (regardless of tenant_id)
- Updates existing users to link them to the tenant
- Sets the role to 'owner' (overriding the 'member' default)

### Issue: "A facade root has not been set"

**Cause**: Rate limiters were defined in `bootstrap/app.php` before facades initialized.

**Solution**: Already fixed - rate limiters moved to `AppServiceProvider::boot()`.

### Issue: Cannot access dashboard pages

**Verify tenant linkage**:
```bash
php artisan tinker
$user = App\Models\User::on('pgsql')->where('email', 'test@example.com')->first();
echo "Tenant ID: " . ($user->tenant_id ?? 'NULL');
```

If `tenant_id` is NULL, run `dashboard:setup-test` again.

## Testing After Setup

1. **Start development server**:
   ```bash
   composer run dev
   ```

2. **Visit dashboard**: http://stash.test

3. **Test these pages**:
   - Dashboard (stats overview)
   - Campaigns (list, create, edit)
   - Documents (list, show)

4. **Verify data**:
   - Stats show correct campaign/document counts
   - Campaign cards display with correct status badges
   - Documents show processing states

## Important Notes

### User Creation Logic

The `createTestUser()` method in `SetupDashboardTest` command:

1. Checks if user exists by **email only** (not email + tenant_id)
2. If user exists:
   - Checks if already linked to correct tenant → skip
   - If linked to wrong/no tenant → update `tenant_id` and `role`
3. If user doesn't exist → create with `tenant_id` and `role` set

This ensures the command is **idempotent** and fixes orphaned test users.

### DeadDrop Tests

The setup command does NOT affect DeadDrop package tests because:
- Tests use `DeadDropTestCase` with `RefreshDatabase` trait
- Tests create their own tenant context via `TenantContext::run()`
- Tests don't rely on web middleware for tenant initialization

All 389 DeadDrop tests continue to pass after setup command changes.

## Next Steps

After setup, you can:
1. Test campaign CRUD operations
2. Upload documents
3. Test processing status updates
4. Verify pagination and filtering
5. Test navigation between pages

## Related Documentation

- [TENANCY_NOTES.md](TENANCY_NOTES.md) - Deep dive into custom multi-tenancy system
- [DASHBOARD_TESTING.md](DASHBOARD_TESTING.md) - Frontend testing patterns
- [DASHBOARD_CHEATSHEET.md](DASHBOARD_CHEATSHEET.md) - Quick reference commands
