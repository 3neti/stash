# Pest v4 Browser Tests Bootstrap Issue - Detailed Analysis

## The Problem

### Error
```
BindingResolutionException: Target class [config] does not exist.
```

### When It Occurs
When running: `./vendor/bin/pest tests/Browser/Dashboard/DashboardTest.php`

### Root Cause Chain

1. **Pest loads test file** → `tests/Browser/Dashboard/DashboardTest.php`
2. **Pest requires helpers** → `require_once __DIR__.'/tests/Browser/Helpers.php'` (from pest.php)
3. **Helpers are evaluated** → PHP code in Helpers.php is executed during inclusion
4. **Helper functions try to use factories** → `User::factory()->create()`
5. **Factory needs Laravel container** → Container tries to resolve bindings
6. **'config' binding doesn't exist** → BindingResolutionException thrown

## Why It Fails

### Traditional Laravel Tests
```
PHPUnit/Pest runs test
  → setUp() method initializes TestCase
    → Boots Laravel application
      → Registers service providers
        → Binds 'config' into container
          → Factories can access container
            → Test code runs ✓
```

### Pest Browser Tests (Current Setup)
```
Pest runs browser test file
  → pest.php includes Helpers.php (before TestCase boots!)
    → Helper functions defined (but not executed yet)
      → Later, test code calls loginTestUser()
        → Helper tries User::factory()->create()
          → Container not initialized yet
            → 'config' binding missing ✗
```

## The Root Issue

**The helpers are being required/parsed during Pest initialization, BEFORE the test class's setUp() method can bootstrap the Laravel application.**

When PHP parses the helper file, it sees:
```php
function loginTestUser(array $attributes = []): Browser
{
    $user = User::factory()->create([  // ← This line...
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ...$attributes,
    ]);
    
    return loginAsUser($user);
}
```

While this function definition itself doesn't execute the factory, the inclusion of the file triggers autoloading and initialization code that needs the Laravel container.

## What You Need to Help With

### Investigation Questions

1. **When exactly does the error occur?**
   - Is it during `require_once` in pest.php?
   - Or during the first test execution?
   - Can you run this and share output?
   ```bash
   php -r "require 'vendor/autoload.php'; require 'tests/Browser/Helpers.php';"
   ```

2. **Check if phpunit.xml or pest.xml affects this**
   - Do you have a `pest.xml` or `phpunit.xml`?
   - What's in the `<php>` section?
   - Does it set APP_ENV?

3. **Test the container manually**
   - Can you run this to see if the container is bootstrapped?
   ```bash
   php artisan tinker --execute="echo app()->environment();"
   ```

4. **Check Pest's configuration**
   - Is there a `pest.xml` we should create?
   - Should we configure Pest to bootstrap the app differently?

### Potential Solutions to Try

#### Solution A: Lazy-load helpers only in tests
Instead of requiring helpers globally in pest.php, require them only within browser tests:

```php
// In DashboardTest.php
beforeEach(function () {
    require_once __DIR__.'/../Helpers.php';
});

test('authenticated user can view dashboard', function () {
    // Now helpers are available after setUp()
});
```

#### Solution B: Move helpers into Helper class
Create a proper Helper class that's instantiated after Laravel boots:

```php
// tests/Browser/TestHelper.php
class TestHelper {
    public static function loginTestUser(array $attributes = []): Browser {
        $user = User::factory()->create([...]);
        return self::loginAsUser($user);
    }
}

// Then in test:
beforeEach(function () {
    $this->helper = new TestHelper();
});

test('...', function () {
    $this->helper->loginTestUser();
});
```

#### Solution C: Use API endpoints for test data
Instead of factories, create test data via HTTP:

```php
function loginTestUser(array $attributes = []): Browser
{
    // Visit a test endpoint that creates a user
    visit('/test-api/create-user')
        ->assertStatus(200);
    
    // Then login normally
    return visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('button[type="submit"]');
}
```

#### Solution D: Initialize Laravel in pest.php
Make sure Laravel is properly booted before helpers are loaded:

```php
// pest.php - at the very top
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Then require helpers
require_once __DIR__.'/tests/Browser/Helpers.php';
```

## What I Need From You

To fix this properly, I need you to try:

1. **Run the helper inclusion test** and report the output:
   ```bash
   php -r "require 'vendor/autoload.php'; require 'tests/Browser/Helpers.php';"
   ```

2. **Check if pest.xml exists**:
   ```bash
   ls -la pest.xml phpunit.xml 2>/dev/null
   ```

3. **Check Pest's documentation** for your Laravel version:
   - Are there any Laravel-specific Pest configurations?
   - Should we create a pest.xml?

4. **Try Solution A** (lazy-load helpers) - this is likely the quickest fix:
   - Modify pest.php to NOT require helpers globally
   - Move the require into individual test files
   - Report if this works

## My Hypothesis

The **most likely fix** is **Solution A: Lazy-loading helpers**.

Pest v4 browser tests are designed to work with a running HTTP server, not with direct Laravel container access during test setup. By deferring helper loading until after Laravel boots (which happens when the test setUp() method runs), we should resolve the bootstrap issue.

Would you like to try this approach? It requires:
1. Removing `require_once __DIR__.'/tests/Browser/Helpers.php';` from pest.php
2. Adding a `beforeEach()` hook in each browser test file to require helpers
3. Testing if it works

**Can you confirm which approach sounds right and try the diagnostic commands I listed above?**
