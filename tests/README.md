# Testing Guide

## Overview

Stash uses **Pest v4** for testing with a comprehensive test suite covering unit, feature, and integration tests.

---

## Test Organization

```
tests/
├── Unit/               # Unit tests (isolated component tests)
│   ├── DeadDrop/      # DeadDrop package unit tests
│   └── App/           # Application unit tests
├── Feature/            # Feature tests (HTTP, workflows)
│   ├── DeadDrop/      # DeadDrop package feature tests
│   ├── Auth/          # Authentication tests
│   └── Settings/      # User settings tests
├── Integration/        # Integration tests (multi-component)
│   └── DeadDrop/      # DeadDrop integration tests
├── TestCase.php       # Base test case for application
└── DeadDropTestCase.php  # Base test case for DeadDrop packages
```

---

## Running Tests

### All Tests
```bash
php artisan test
```

### With Coverage
```bash
php artisan test --coverage
```

### Minimum Coverage Threshold
```bash
php artisan test --coverage --min=80
```

### Specific Test Suite
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration
```

### Specific Test File
```bash
php artisan test tests/Feature/Auth/LoginTest.php
```

### Filter by Test Name
```bash
php artisan test --filter="user can login"
```

### Parallel Testing
```bash
php artisan test --parallel
```

---

## Test Configuration

### Database

**Local Development**: SQLite in-memory database (`:memory:`)
- Fast and isolated
- No setup required
- Automatically configured in `phpunit.xml`

**Sail/Docker (Meta-Campaign)**: PostgreSQL
- Production-like environment
- Used for integration testing with full service stack
- See `DEVELOPMENT.md` for Sail setup

### Environment Variables

Tests use configuration from `phpunit.xml`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
```

---

## Writing Tests

### Unit Test Example

```php
<?php

test('example unit test', function () {
    expect(true)->toBeTrue();
});
```

### Feature Test Example

```php
<?php

use App\Models\User;

test('authenticated user can access dashboard', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->get('/dashboard');
    
    $response->assertOk();
});
```

### DeadDrop Package Test Example

Create tests in `tests/Unit/DeadDrop/` or `tests/Feature/DeadDrop/`:

```php
<?php

use LBHurtado\DeadDrop\Models\Campaign;
use LBHurtado\DeadDrop\Models\Subscriber;

test('campaign belongs to subscriber', function () {
    $subscriber = Subscriber::factory()->create();
    $campaign = Campaign::factory()->for($subscriber)->create();
    
    expect($campaign->subscriber)->toBeInstanceOf(Subscriber::class)
        ->and($campaign->subscriber->id)->toBe($subscriber->id);
});
```

---

## Test Helpers

### Factories

Use factories for test data:

```php
// Create a user
$user = User::factory()->create();

// Create with specific attributes
$user = User::factory()->create([
    'email' => 'test@example.com',
]);

// Create multiple
$users = User::factory()->count(10)->create();
```

### RefreshDatabase

All feature and integration tests automatically use `RefreshDatabase` trait:

```php
// Database is automatically migrated before each test
// and rolled back after each test
```

### Acting As User

```php
$user = User::factory()->create();

$this->actingAs($user)
    ->get('/dashboard')
    ->assertOk();
```

---

## Pest v4 Features

### Expectations

```php
expect($value)->toBe(1);
expect($value)->toBeTrue();
expect($array)->toHaveCount(5);
expect($collection)->toContain('item');
```

### Datasets

```php
dataset('emails', [
    'valid@example.com',
    'another@test.com',
]);

test('validates email', function (string $email) {
    expect(filter_var($email, FILTER_VALIDATE_EMAIL))->toBeString();
})->with('emails');
```

### Hooks

```php
beforeEach(function () {
    // Runs before each test
    $this->user = User::factory()->create();
});

afterEach(function () {
    // Runs after each test
});
```

### Test Lifecycle

```php
beforeAll(function () {
    // Runs once before all tests in file
});

afterAll(function () {
    // Runs once after all tests in file
});
```

---

## Continuous Integration

Tests run automatically on GitHub Actions for:
- **PHP Versions**: 8.2, 8.3, 8.4
- **Test Coverage**: Minimum 80% required
- **Code Style**: Laravel Pint checks
- **Frontend**: ESLint, Prettier, build verification

### CI Workflow

See `.github/workflows/tests.yml` for full configuration.

**Jobs**:
1. **tests** - Run Pest test suite on multiple PHP versions
2. **pint** - Check code formatting
3. **frontend** - Lint and build frontend assets

---

## Best Practices

### 1. Descriptive Test Names

```php
// Good
test('authenticated user can create campaign')

// Bad
test('test create')
```

### 2. Arrange-Act-Assert Pattern

```php
test('user can update profile', function () {
    // Arrange
    $user = User::factory()->create();
    
    // Act
    $response = $this->actingAs($user)
        ->put('/settings/profile', [
            'name' => 'New Name',
        ]);
    
    // Assert
    $response->assertOk();
    expect($user->fresh()->name)->toBe('New Name');
});
```

### 3. One Assertion Per Concept

```php
test('campaign has required attributes', function () {
    $campaign = Campaign::factory()->create();
    
    expect($campaign->name)->toBeString()
        ->and($campaign->slug)->toBeString()
        ->and($campaign->status)->toBe('active');
});
```

### 4. Use Factories

```php
// Good
$user = User::factory()->create();

// Avoid
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password'),
]);
```

### 5. Mock External Services

```php
use Illuminate\Support\Facades\Http;

test('fetches external data', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => 'value'], 200),
    ]);
    
    // Your test code
});
```

---

## Coverage Requirements

### Minimum Thresholds

- **Lines**: 80%
- **Methods**: 80%
- **Branches**: 70%

### Critical Paths (100% Required)

- Credential vault resolution
- Multi-tenancy scoping
- Meta-Campaign validation pipeline
- Authentication/authorization logic

### Generate Coverage Report

```bash
# HTML report
php artisan test --coverage-html=coverage

# Open in browser
open coverage/index.html
```

---

## Troubleshooting

### Tests Failing Locally

1. Clear config cache:
   ```bash
   php artisan config:clear
   ```

2. Ensure migrations are up to date:
   ```bash
   php artisan migrate:fresh
   ```

3. Check `.env` file exists:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

### Database Connection Errors

Tests use SQLite in-memory by default. If you see database errors:
- Check `phpunit.xml` has `DB_CONNECTION=sqlite`
- Ensure SQLite PHP extension is installed

### Slow Tests

```bash
# Run tests in parallel
php artisan test --parallel

# Run only fast tests
php artisan test --exclude-group=slow
```

---

## Additional Resources

- [Pest Documentation](https://pestphp.com)
- [Laravel Testing](https://laravel.com/docs/12.x/testing)
- [Testing Guidelines](.ai/guidelines/stash/testing.md)
