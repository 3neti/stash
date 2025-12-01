# TenancyService Integration in Controllers

## Answer: Yes, Controllers ARE Using TenancyService ✅

The UI controllers **do not directly import or call TenancyService**, but they **automatically benefit from it** through the middleware-based initialization pattern. This is the correct architectural design.

## Architecture: Middleware-Based Tenant Initialization

```
HTTP Request → InitializeTenantFromUser Middleware → TenancyService → Controllers
```

### Flow

1. **HTTP Request arrives**
   - Browser makes request to `http://stash.test:8000/campaigns`

2. **Middleware: InitializeTenantFromUser** (`app/Http/Middleware/InitializeTenantFromUser.php`)
   ```php
   public function handle(Request $request, Closure $next): Response
   {
       $user = $request->user();
       
       if ($user && $user->tenant_id) {
           // Resolve TenancyService from container
           $tenancyService = app(TenancyService::class);
           
           // Initialize tenant (creates DB, runs migrations, sets up context)
           $tenancyService->initializeTenant($tenant);
       }
       
       return $next($request);  // → Controller executes with tenant context active
   }
   ```

3. **TenancyService Execution**
   - `initializeTenant($tenant)`
     - Ensures tenant database exists
     - Runs pending migrations on tenant DB
     - Verifies schema has required tables
     - Sets up TenantContext (connection switching)
     - Fires `TenantDatabasePrepared` event

4. **Controller Executes** (with tenant context initialized)
   ```php
   class CampaignController extends Controller
   {
       public function index(): Response
       {
           // Tenant context is ALREADY initialized
           $campaigns = ListCampaigns::run(
               status: request('status'),
               search: request('search'),
               perPage: 15
           );
           
           return Inertia::render('campaigns/Index', ['campaigns' => $campaigns]);
       }
   }
   ```

5. **Models Query Tenant Database**
   - `Campaign::query()` automatically uses 'tenant' connection
   - `BelongsToTenant` trait on Campaign model ensures this
   - All queries target the correct tenant database

6. **Response Returned**
   - Middleware cleans up: `TenantContext::forgetCurrent()`
   - Response sent to browser

## Controllers Using This Pattern

### Web Controllers
- **CampaignController** (`app/Http/Controllers/CampaignController.php`)
  - `index()` - List all campaigns
  - `create()` - Show create form
  - `store()` - Create campaign
  - `show()` - View campaign details
  - `edit()` - Show edit form
  - `update()` - Update campaign
  - `destroy()` - Delete campaign

- **DocumentController** (`app/Http/Controllers/DocumentController.php`)
  - `index()` - List all documents
  - `show()` - View document details

- **DashboardController** (`app/Http/Controllers/DashboardController.php`)
  - `index()` - Dashboard view

### Settings Controllers
- **ProfileController** (`app/Http/Controllers/Settings/ProfileController.php`)
- **PasswordController** (`app/Http/Controllers/Settings/PasswordController.php`)
- **TwoFactorAuthenticationController** (`app/Http/Controllers/Settings/TwoFactorAuthenticationController.php`)

All use the same middleware initialization pattern.

## Middleware Registration

**Location**: `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        HandleAppearance::class,
        InitializeTenantFromUser::class,  // ← Tenant initialization here
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

The middleware is registered as **global web middleware**, so it runs on every authenticated request.

## Why This Design Is Correct

### ✅ Separation of Concerns
- Controllers don't know about tenancy
- Middleware handles tenant initialization transparently
- Business logic (campaigns, documents) stays clean

### ✅ Consistent Initialization
- Every request ensures tenant is initialized
- No "forgot to initialize" bugs
- Middleware runs before controller, guarantees safety

### ✅ Testability
- TenancyService can be unit tested in isolation
- Controllers can be tested with mocked tenant
- Integration tests verify full middleware → controller flow

### ✅ Extensibility
- Adding new controllers? Automatic tenant support via middleware
- No per-controller configuration needed
- Future tenancy features only update TenancyService and middleware

## Test Evidence: Controllers Working

```bash
$ php artisan test --filter campaign
Tests:    66 passed (168 assertions)

PASS  Tests\Feature\DeadDrop\CampaignAccessWithoutSQLSTATETest
  ✓ campaign detail page loads successfully

PASS  Tests\Feature\DeadDrop\CampaignDetailRouteTest
  ✓ authenticated user can view campaign detail page
  ✓ authenticated user can view campaign edit page
  ✓ authenticated user can delete campaign

PASS  Tests\Browser\Campaigns\CampaignTest
  ✓ authenticated user can view campaign detail page without database errors
```

No `SQLSTATE[42P01]` errors = tenant context is properly initialized by middleware ✅

## Debug Logging

To verify TenancyService is being called by middleware:

```bash
# Watch logs while making a request
php artisan pail --filter="Middleware|TenancyService"

# Then visit http://stash.test:8000/campaigns in browser
```

**Expected logs**:
```
[Middleware] InitializeTenantFromUser
  → Looking up tenant
  → Initializing tenant context
[TenancyService] Initializing tenant
  → Preparing database
  → Database prepared
[TenancyService] Context initialized
```

## Dataflow Summary

```
┌─────────────────────────────────────────────────────────────┐
│ HTTP Request to /campaigns                                  │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ InitializeTenantFromUser Middleware                         │
│  • Extract tenant_id from authenticated user                │
│  • Load Tenant from central database                         │
│  • Call TenancyService::initializeTenant()                  │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ TenancyService::initializeTenant()                          │
│  ✓ Create tenant DB if missing                             │
│  ✓ Run migrations on tenant DB                             │
│  ✓ Verify schema has required tables                       │
│  ✓ Switch database connection to tenant                    │
│  ✓ Fire TenantDatabasePrepared event                       │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ CampaignController::index()                                 │
│  • ListCampaigns::run() - queries tenant DB                 │
│  • Campaign::query() uses 'tenant' connection               │
│  • Returns campaigns from correct tenant                    │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ Response sent to browser                                    │
│ TenantContext::forgetCurrent() - cleanup                    │
└─────────────────────────────────────────────────────────────┘
```

## Conclusion

✅ **Yes, controllers are using TenancyService**
- Indirectly through middleware initialization
- This is the correct pattern for Laravel applications
- All tenant setup happens before controller code runs
- No SQLSTATE errors because tenant is ready
- Tests confirm 66 campaign/document operations passing

The architecture is clean, testable, and extensible! 🎉
