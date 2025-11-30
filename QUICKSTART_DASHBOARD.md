# Dashboard Testing - Quick Start

**5-minute setup for Phase 3.1 Subscriber Dashboard visual testing**

## Step 1: Setup (One Command)

```bash
php artisan dashboard:setup-test
```

This command will:
- ✅ Create tenant "Test Company" 
- ✅ Run all tenant migrations
- ✅ Create test user
- ✅ Seed 6 campaigns + 23 documents

**Expected output:**
```
🚀 Setting up Dashboard Test Environment...

🏢 Creating tenant: Test Company
✓ Tenant created: test-company
  Domain: test.localhost

📦 Running tenant migrations...
✓ Tenant database migrated

🌱 Seeding test data...
✓ Created test user: test@example.com
✓ Created 6 campaigns
✓ Created 23 documents

✅ Dashboard test environment ready!

📝 Login URL     http://127.0.0.1:8000
📧 Email         test@example.com
🔑 Password      password
```

## Step 2: Start Server

```bash
composer run dev
```

**Or individually:**
```bash
php artisan serve    # Terminal 1: Laravel on :8000
npm run dev          # Terminal 2: Vite HMR
```

## Step 3: Test!

1. Visit: http://stash.test (Herd)
2. Login: `test@example.com` / `password`
3. Explore:
   - Dashboard with stats
   - Campaigns list (create, edit, delete)
   - Documents list (view details)

## Quick Commands

```bash
# Fresh start (delete everything)
php artisan dashboard:setup-test --fresh

# Re-seed without recreating tenant
php artisan tinker
$t = App\Models\Tenant::on('pgsql')->first();
App\Tenancy\TenantContext::run($t, fn() => Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\DashboardTestSeeder',
    '--database' => 'tenant'
]));

# Check logs
php artisan pail
```

## Test Data

- **6 Campaigns**: 4 active (3 custom + 1 template), 2 paused (custom)
- **23 Documents**: Various statuses (pending, processing, completed, failed)
- **Test User**: test@example.com / password (role: owner)

## Routes to Test

| Route | Description |
|-------|-------------|
| `/dashboard` | Stats overview |
| `/campaigns` | Campaign list with search |
| `/campaigns/create` | Create new campaign |
| `/campaigns/{id}` | Campaign detail + document upload |
| `/campaigns/{id}/edit` | Edit campaign |
| `/documents` | All documents with search |
| `/documents/{uuid}` | Document detail |

## Troubleshooting

**Problem**: "Relation 'campaigns' does not exist"  
**Fix**: `php artisan dashboard:setup-test`

**Problem**: Can't login  
**Fix**: User not in tenant DB, re-run setup

**Problem**: Frontend not updating  
**Fix**: `npm run dev` (for hot reload)

## Full Documentation

- **Complete Guide**: `DASHBOARD_TESTING.md`
- **Cheat Sheet**: `DASHBOARD_CHEATSHEET.md`
- **Tenancy Info**: `TENANCY_NOTES.md`

---

**That's it!** 🎉 You should now have a fully functional dashboard to test.
