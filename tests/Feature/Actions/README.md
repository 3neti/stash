# Action Tests

## Test Database Setup

These tests require a PostgreSQL test database. If you see errors like "Call to a member function connection() on null", the test database needs to be created.

### Setup Instructions

1. **Create test database**:
```bash
createdb -h 127.0.0.1 -U postgres stash_test
```

2. **Run migrations**:
```bash
php artisan migrate --env=testing
```

3. **Run tests**:
```bash
php artisan test --filter ImportCampaignTest
```

### Configuration

Test database settings are in `.env.testing`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stash_test
DB_USERNAME=postgres
DB_PASSWORD=password
```

## ImportCampaignTest

Tests the `ImportCampaign` action which imports campaigns from JSON/YAML files via DTOs.

**Coverage**:
- ✓ Imports valid campaign data
- ✓ Auto-generates slug from name
- ✓ Uses provided slug
- ✓ Maps state strings to state classes
- ✓ Validates processor types exist
- ✓ Validates unique step IDs
- ✓ Rejects missing required fields
- ✓ Rejects invalid enum values
- ✓ Imports with optional fields
- ✓ Creates in tenant database

**Test Count**: 13 tests

## Running Tests

```bash
# Run all action tests
php artisan test tests/Feature/Actions

# Run specific test
php artisan test --filter ImportCampaignTest

# Run with compact output
php artisan test --filter ImportCampaignTest --compact
```
