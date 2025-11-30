# Phase 3.1 Dashboard Testing Guide

Complete guide for visually testing the Subscriber Dashboard implementation.

## Quick Start (Recommended)

### Option 1: Using Artisan Command
```bash
# Create tenant and seed test data
php artisan dashboard:setup-test

# Start development server
composer run dev
```

### Option 2: Using Bash Script
```bash
# One-command setup
./setup-dashboard-test.sh

# Start development server
composer run dev
```

### Option 3: Fresh Installation
If you need to start completely fresh:

```bash
php artisan dashboard:setup-test --fresh
# OR
./setup-dashboard-test.sh --fresh
```

## What Gets Created

The setup command automatically:
- ✅ Creates a test tenant ("Test Company")
- ✅ Runs all tenant migrations
- ✅ Creates test user: `test@example.com` / `password`
- ✅ Seeds 6 campaigns (4 active, 2 inactive)
- ✅ Seeds 23 documents with various statuses
- ✅ Creates document jobs with processing history

## Test Data Overview

### Campaigns Created
| Name | Status | Type | Documents |
|------|--------|------|-----------|
| Campaign 1-3 | Active | Custom | 5 each |
| Campaign 4-5 | Paused | Custom | 2 each |
| Invoice Processing | Active | Template | 5 |

### Document Statuses
- **Pending**: Documents waiting to be processed
- **Processing**: Documents currently in pipeline
- **Completed**: Successfully processed documents
- **Failed**: Documents with processing errors

## Accessing the Dashboard

### 1. Start the Development Server

**All-in-one (Recommended)**:
```bash
composer run dev
```
This starts: Laravel server, Vite HMR, queue worker, and log viewer.

**Individual services**:
```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Vite (for hot reload)
npm run dev

# Terminal 3: Queue worker (optional)
php artisan queue:work

# Terminal 4: Logs (optional)
php artisan pail
```

### 2. Visit the Application

**URL**: http://127.0.0.1:8000

**Login Credentials**:
- Email: `test@example.com`
- Password: `password`

### 3. Custom Domain (Optional)

Add to `/etc/hosts`:
```
127.0.0.1 test.localhost
```

Then visit: http://test.localhost:8000

## Testing Checklist

### Dashboard Page (`/dashboard`)
- [ ] See 4 stat cards with correct counts
  - Total Campaigns
  - Total Documents  
  - Processing Count
  - Completed Count
- [ ] Quick Actions card with "New Campaign" and "Upload Documents" links
- [ ] All icons and styling render correctly

### Campaigns List (`/campaigns`)
- [ ] See grid of campaign cards
- [ ] Search functionality works
- [ ] Active/Inactive badges display correctly
- [ ] Document counts show on each card
- [ ] "New Campaign" button visible
- [ ] Click "View Details" navigates to campaign page

### Create Campaign (`/campaigns/create`)
- [ ] Form fields: Name, Description, Type
- [ ] Form validation shows errors
- [ ] "Create Campaign" button creates new campaign
- [ ] Redirect to campaign detail page after creation

### Campaign Detail (`/campaigns/{id}`)
- [ ] Campaign name and status badge display
- [ ] Pipeline visualizer shows processing stages
- [ ] Document uploader component visible
- [ ] Can select multiple files (max 10)
- [ ] Documents list shows with status badges
- [ ] Edit and Delete buttons visible
- [ ] Click document navigates to document detail

### Edit Campaign (`/campaigns/{id}/edit`)
- [ ] Form pre-filled with campaign data
- [ ] Can change name, description, type
- [ ] Status radio buttons (Active/Inactive) work
- [ ] "Save Changes" updates campaign
- [ ] "Cancel" returns to campaign detail

### Delete Campaign
- [ ] Click delete button shows confirmation
- [ ] Confirming deletes campaign
- [ ] Redirects to campaigns list

### Documents List (`/documents`)
- [ ] See list of all documents
- [ ] Search by filename works
- [ ] Status badges show correctly (pending/processing/completed/failed)
- [ ] File sizes display
- [ ] Campaign names show for each document
- [ ] Click document navigates to detail page

### Document Detail (`/documents/{uuid}`)
- [ ] Document filename and metadata display
- [ ] File size and MIME type visible
- [ ] UUID shown
- [ ] Campaign link navigates to campaign page
- [ ] Processing job status displays
- [ ] Job timestamps (started/completed/failed) show
- [ ] Error messages display for failed documents
- [ ] Metadata JSON viewer (if metadata exists)

### Navigation
- [ ] Sidebar shows Dashboard, Campaigns, Documents menu items
- [ ] Active route highlighted in sidebar
- [ ] Breadcrumbs show correct path
- [ ] User dropdown in sidebar footer
- [ ] All navigation links work

### Responsive Design
- [ ] Dashboard responsive on mobile
- [ ] Campaign grid stacks on mobile
- [ ] Document list readable on mobile
- [ ] Sidebar collapsible

## Manual Testing Scenarios

### Scenario 1: Create and Manage Campaign
1. Login with test credentials
2. Click "New Campaign" from dashboard quick actions
3. Fill out form: "Test Campaign", "Testing workflow", "custom"
4. Click "Create Campaign"
5. Verify redirect to campaign detail page
6. Click "Edit" button
7. Change status to "Paused"
8. Click "Save Changes"
9. Verify badge shows "Inactive"
10. Navigate back to campaigns list
11. Verify campaign shows in list

### Scenario 2: Upload Documents
1. Go to any active campaign detail page
2. Click document uploader area
3. Select 3 files (or use fake files)
4. Click "Upload Documents"
5. Wait for upload (note: API upload may fail without actual campaign token setup)
6. Verify documents appear in list (if upload succeeds)

### Scenario 3: Browse Documents
1. Click "Documents" in sidebar
2. Use search to filter by filename
3. Click on a completed document
4. Verify all metadata displays
5. Click campaign link
6. Verify navigation to correct campaign

### Scenario 4: Dashboard Stats
1. Go to dashboard
2. Note stat card values
3. Create new campaign
4. Return to dashboard
5. Verify "Total Campaigns" increased by 1

## Troubleshooting

### Issue: "Relation 'campaigns' does not exist"
**Solution**: Tenant migrations not run. Use the setup command:
```bash
php artisan dashboard:setup-test
```

### Issue: Can't login
**Solution**: User not created in tenant database:
```bash
php artisan tinker
$tenant = App\Models\Tenant::on('pgsql')->first();
App\Tenancy\TenantContext::run($tenant, function() {
    App\Models\User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password')
    ]);
});
```

### Issue: Changes not appearing
**Solution**: Rebuild assets:
```bash
npm run build
# OR for hot reload:
npm run dev
```

### Issue: Document upload fails
**Expected**: Document upload via DocumentUploader uses API endpoint which requires campaign authentication token. This is expected in testing environment. You can still see documents created by seeder.

### Issue: No test data
**Solution**: Re-run seeder:
```bash
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

## Clean Slate

To start completely fresh:

```bash
# Drop everything and start over
php artisan dashboard:setup-test --fresh
```

Or manually:
```bash
# Reset central database
php artisan migrate:fresh --force

# Run setup again
php artisan dashboard:setup-test
```

## Advanced: Manual Setup

If you prefer manual control:

```bash
# 1. Create tenant (migrations run automatically)
php artisan tenant:create "My Tenant" --domain=my.localhost

# 2. Seed data
php artisan tinker
$tenant = App\Models\Tenant::on('pgsql')->where('slug', 'my-tenant')->first();
App\Tenancy\TenantContext::run($tenant, function() {
    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\DashboardTestSeeder',
        '--database' => 'tenant'
    ]);
});
exit
```

## Next Steps

After visual testing:
- Test with real file uploads (requires S3 or local storage setup)
- Test with actual queue workers processing documents
- Test with multiple tenants
- Test API endpoints with campaign tokens
- Load testing with many campaigns/documents

## Support

If you encounter issues:
1. Check `storage/logs/laravel.log`
2. Use `php artisan pail` for real-time logs
3. Verify tenant context: `php artisan tinker` → `DB::connection()->getDatabaseName()`
4. Run tests: `php artisan test`
