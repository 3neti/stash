# Dashboard Testing Cheat Sheet

Quick reference for Phase 3.1 Subscriber Dashboard testing.

## 🚀 Quick Start

```bash
# One-command setup
php artisan dashboard:setup-test

# Start dev server
composer run dev

# Login at http://127.0.0.1:8000
# Email: test@example.com
# Password: password
```

## 📋 Common Commands

### Setup & Reset
```bash
# Fresh install (deletes all data)
php artisan dashboard:setup-test --fresh

# Alternative: bash script
./setup-dashboard-test.sh
./setup-dashboard-test.sh --fresh
```

### Development
```bash
# Start all services
composer run dev

# Individual services
php artisan serve          # Laravel :8000
npm run dev                # Vite HMR
php artisan queue:work     # Queue worker
php artisan pail           # Live logs
```

### Database
```bash
# Central DB migrations
php artisan migrate

# Tenant migrations (done automatically by tenant:create)
# Or manually for existing tenant:
php artisan tinker
$tenant = App\Models\Tenant::on('pgsql')->first();
App\Tenancy\TenantContext::run($tenant, function() {
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant'
    ]);
});
exit

# Re-seed test data
php artisan tinker
$tenant = App\Models\Tenant::on('pgsql')->first();
App\Tenancy\TenantContext::run($tenant, function() {
    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\DashboardTestSeeder',
        '--database' => 'tenant'
    ]);
});
exit
```

### Build
```bash
# Production build
npm run build

# Check PHP formatting
./vendor/bin/pint --test

# Fix PHP formatting
./vendor/bin/pint
```

### Testing
```bash
# All tests
php artisan test

# Dashboard tests only
php artisan test --filter Dashboard

# Campaign tests
php artisan test --filter Campaign
```

## 🗺️ Routes

| Page | URL | Description |
|------|-----|-------------|
| Dashboard | `/dashboard` | Stats overview |
| Campaigns List | `/campaigns` | All campaigns |
| Create Campaign | `/campaigns/create` | New campaign form |
| Campaign Detail | `/campaigns/{id}` | Single campaign |
| Edit Campaign | `/campaigns/{id}/edit` | Edit form |
| Documents List | `/documents` | All documents |
| Document Detail | `/documents/{uuid}` | Single document |

## 🔑 Test Credentials

```
Email: test@example.com
Password: password
```

## 📊 Test Data Generated

- **6 Campaigns**: 4 active, 2 inactive
- **23 Documents**: Various statuses (pending, processing, completed, failed)
- **Document Jobs**: With timestamps and error messages

## 🐛 Quick Fixes

### Can't Login
```bash
# Verify user exists
php artisan tinker
$tenant = App\Models\Tenant::on('pgsql')->first();
App\Tenancy\TenantContext::run($tenant, function() {
    return App\Models\User::where('email', 'test@example.com')->first();
});
```

### Missing Tables
```bash
php artisan dashboard:setup-test
```

### Frontend Not Updating
```bash
npm run build
# OR for hot reload
npm run dev
```

### Clear Everything
```bash
php artisan dashboard:setup-test --fresh
```

## 📁 Key Files

### Backend
- `app/Actions/Dashboard/GetDashboardStats.php` - Dashboard stats
- `app/Actions/Campaigns/Web/` - Campaign actions
- `app/Actions/Documents/Web/` - Document actions
- `app/Http/Controllers/CampaignController.php`
- `app/Http/Controllers/DocumentController.php`
- `database/seeders/DashboardTestSeeder.php`

### Frontend
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/campaigns/` - Campaign pages
- `resources/js/pages/documents/` - Document pages
- `resources/js/components/CampaignCard.vue`
- `resources/js/components/DocumentUploader.vue`
- `resources/js/components/ProcessingStatusBadge.vue`
- `resources/js/components/PipelineVisualizer.vue`

## 🔍 Debugging

```bash
# Real-time logs
php artisan pail

# Check tenant context
php artisan tinker
DB::connection()->getDatabaseName()

# View routes
php artisan route:list

# View test output
php artisan test --filter Dashboard -v
```

## ✅ Testing Checklist

Quick validation:
- [ ] Login works
- [ ] Dashboard shows stats
- [ ] Campaign list displays
- [ ] Can create campaign
- [ ] Can edit campaign
- [ ] Can delete campaign
- [ ] Documents list displays
- [ ] Document detail shows
- [ ] Navigation works
- [ ] Search works

## 💡 Tips

- Use `composer run dev` for best development experience (all services)
- Vite HMR provides instant frontend updates
- Check `storage/logs/laravel.log` for errors
- Document uploader uses API endpoint (may need tokens for real uploads)
- Test data is safe to delete/recreate anytime

## 📚 Documentation

- Full guide: `DASHBOARD_TESTING.md`
- Setup command: `php artisan dashboard:setup-test --help`
- Tests: `php artisan test --help`
