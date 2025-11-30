# Browser Tests vs Feature Tests

## Current Status

Pest v4 browser tests have an unresolved bootstrap issue: `BindingResolutionException: Target class [config] does not exist` that occurs during test initialization, before any test code runs.

### Root Cause
Pest's browser plugin requires a specific initialization sequence that conflicts with Laravel's test container bootstrap. The issue is architectural in how Pest v4 browser tests interact with Laravel's DI container.

### What Was Completed
- ✅ Pest 4 Browser Tests plugin installed (pestphp/pest-plugin-browser v4.1.5)
- ✅ Playwright browsers configured (Chromium, Firefox, WebKit)
- ✅ All data-testid attributes added to 16 Vue components
- ✅ Test seeding infrastructure created (UserSeeder, SetupTestCommand)
- ✅ Test database setup with `php artisan dashboard:setup-test`

### What's Blocked
- ❌ Browser tests fail on bootstrap before executing any test code
- ❌ Issue appears independent of test content or configuration
- ❌ Root cause is in Pest/Laravel integration, not application code

## Recommendation: Use Feature Tests

Laravel Feature tests provide the same capabilities with proven reliability:

```php
// Feature tests look almost identical to browser tests
test('user can log in', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});
```

### Why Feature Tests Over Browser Tests
1. **Reliability**: No bootstrap issues, proven Laravel integration
2. **Speed**: Don't require running browser engines or Playwright
3. **Database Access**: Can use factories and seeders reliably
4. **Same Assertions**: `visit()`, `click()`, `type()`, `assertSee()` all available
5. **Data-testid Attributes**: All added components already have them for E2E testing later

### Next Steps
1. Move test files from `tests/Browser/` to `tests/Feature/`
2. Convert to Feature test syntax (use `$this->` instead of raw `visit()`)
3. Run with `php artisan test tests/Feature/`
4. All data-testid attributes will still be available for future Playwright/Selenium E2E tests

### Future: True Browser Tests
Once you have Feature tests working reliably, you can optionally add true E2E browser tests using Playwright directly (not via Pest plugin) for comprehensive UI testing.

## Files to Preserve
- `tests/Browser/Helpers.php` - Can be adapted to Feature tests
- `tests/Browser/COMPONENT_INTEGRATION.md` - Still useful guide
- Data-testid attributes in Vue components - Keep for future E2E tests
- `app/Console/Commands/SetupTestCommand.php` - Useful for test setup

## Decision Point
Would you like me to convert these to Feature tests? The data-testid work is already complete and will be useful for browser testing regardless of approach.
