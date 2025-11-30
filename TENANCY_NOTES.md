# Custom Tenancy System

This project uses a **custom multi-tenancy implementation** (not stancl/tenancy).

## Architecture

### Key Components

- **`App\Tenancy\TenantContext`**: Manages current tenant context throughout request lifecycle
- **`App\Tenancy\TenantConnectionManager`**: Handles database connection switching and tenant database CRUD
- **`App\Tenancy\Traits\BelongsToTenant`**: Trait for models that live in tenant database
- **`App\Models\Tenant`**: Central tenant model (lives in central database)

### Database Structure

- **Central Database** (`pgsql` connection): 
  - `tenants` table
  - `domains` table
  - `users` table (with `tenant_id` foreign key)
  
- **Tenant Databases** (`tenant` connection):
  - Separate PostgreSQL database per tenant
  - Named: `tenant_{ULID}`
  - Contains: campaigns, documents, document_jobs, processors, etc.

## Usage

### Creating a Tenant

```bash
# Create tenant with automatic migrations
php artisan tenant:create "Company Name" --domain=company.localhost

# Skip migrations
php artisan tenant:create "Company Name" --skip-migrations
```

### Switching Tenant Context

```php
use App\Tenancy\TenantContext;
use App\Models\Tenant;

// Get tenant from central DB
$tenant = Tenant::on('pgsql')->where('slug', 'company-name')->first();

// Initialize tenant context
TenantContext::initialize($tenant);

// Now all queries use tenant database
$campaigns = Campaign::all();  // Queries tenant_abc123.campaigns

// Forget tenant context
TenantContext::forgetCurrent();
```

### Running Code in Tenant Context

```php
use App\Tenancy\TenantContext;

TenantContext::run($tenant, function() {
    // Code here runs with tenant database
    Campaign::factory(10)->create();
    
    Artisan::call('db:seed', [
        '--class' => 'SomeSeeder',
        '--database' => 'tenant'
    ]);
});

// Context automatically restored after callback
```

### Running Migrations

```php
// Migrations run automatically when creating tenant
php artisan tenant:create "Company" --domain=company.localhost

// Or manually for existing tenant
TenantContext::run($tenant, function() {
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant',
        '--force' => true
    ]);
});
```

## Models

### Central Database Models

Models that live in the central database use standard Eloquent:

```php
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'pgsql';  // Central DB
    // ...
}
```

### Tenant Database Models

Models that live in tenant databases use `BelongsToTenant` trait:

```php
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use BelongsToTenant;  // Automatically uses 'tenant' connection
    // ...
}
```

## Important Notes

### Always Specify Connection for Central Models

When working with central models from tenant context:

```php
// GOOD - Explicitly use central connection
$tenant = Tenant::on('pgsql')->find($id);

// BAD - Will try to use tenant connection
$tenant = Tenant::find($id);  // ERROR!
```

### Seeding Tenant Data

Always run seeders with tenant context:

```php
TenantContext::run($tenant, function() {
    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\YourSeeder',
        '--database' => 'tenant',  // Important!
    ]);
});
```

### Testing

Tests that need tenant data should use tenant context:

```php
public function test_example()
{
    $tenant = Tenant::factory()->create();
    
    TenantContext::run($tenant, function() {
        $campaign = Campaign::factory()->create();
        
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id
        ]);
    });
}
```

## Differences from stancl/tenancy

| Feature | stancl/tenancy | Custom Implementation |
|---------|---------------|----------------------|
| Package | External package | Built-in |
| Context Management | `Tenancy::initialize()` | `TenantContext::initialize()` |
| Running in Context | Middleware | `TenantContext::run()` |
| Connection Name | Configurable | Always `tenant` |
| Migration Path | `database/migrations` | `database/migrations/tenant` |
| Tenant Model | `Stancl\Tenancy\Database\Models\Tenant` | `App\Models\Tenant` |

## Dashboard Testing

The `dashboard:setup-test` command uses this custom tenancy system:

```bash
# Full setup with tenant creation and seeding
php artisan dashboard:setup-test

# Fresh installation
php artisan dashboard:setup-test --fresh
```

See `DASHBOARD_TESTING.md` for complete testing guide.
