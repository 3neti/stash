# Custom Validation Rules

Comprehensive guide to the user-defined custom validation rules system in Stash/DeadDrop.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Rule Types](#rule-types)
  - [Regex Rules (Phase 1)](#regex-rules-phase-1)
  - [Expression Rules (Phase 2)](#expression-rules-phase-2)
  - [Callback Rules (Phase 3)](#callback-rules-phase-3)
- [Database Schema](#database-schema)
- [Creating Custom Rules](#creating-custom-rules)
- [Using Custom Rules](#using-custom-rules)
- [Expression Language Reference](#expression-language-reference)
- [Examples](#examples)
- [API Reference](#api-reference)
- [Performance & Caching](#performance--caching)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)

---

## Overview

The Custom Validation Rules system allows users to define their own validation logic without writing PHP code. Rules are stored in the database, tenant-specific, and can be applied to CSV imports, form validations, and API requests.

### Key Features

- **Three rule types**: Regex (pattern matching), Expression (multi-field logic), Callback (future)
- **Tenant-specific**: Each tenant has isolated rule namespaces
- **Cached**: Rules loaded once per request and cached in Redis
- **Safe**: Expression Language provides sandboxed execution
- **Flexible**: Works with Laravel's validation system seamlessly
- **Reusable**: Define once, use across multiple campaigns/forms

### Use Cases

- CSV import validation with complex business rules
- Form validation with multi-field dependencies
- API request validation with custom domain logic
- Data quality enforcement across the platform

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Custom Validation Rules                  │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │   Database   │───▶│   Registry   │───▶│  Processor   │  │
│  │   Storage    │    │   (Cache)    │    │  Validation  │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│         │                    │                    │          │
│         │                    │                    │          │
│  custom_validation_rules  In-Memory Cache   CsvImportProcessor
│  (PostgreSQL)             (per request)     (applies rules)  │
│                                                               │
└─────────────────────────────────────────────────────────────┘

Flow:
1. Admin creates rule via UI/API → Stored in central database
2. Processor loads rules → Cached in CustomRuleRegistry
3. CSV row validated → Rule executed with row context
4. Result: Pass (import) or Fail (skip row)
```

### Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **CustomValidationRule** | Eloquent model, validates values | `app/Models/CustomValidationRule.php` |
| **CustomRuleRegistry** | In-memory cache, loads rules | `app/Services/Validation/CustomRuleRegistry.php` |
| **CsvImportProcessor** | Applies rules during import | `app/Services/Processors/PortPHP/CsvImportProcessor.php` |
| **Migration** | Database schema | `database/migrations/2025_12_03_041107_create_custom_validation_rules_table.php` |
| **Seeder** | Example rules | `database/seeders/CustomValidationRuleSeeder.php` |

---

## Rule Types

### Regex Rules (Phase 1)

**Status**: ✅ Implemented

Pattern-based validation using regular expressions. Best for single-field format validation.

#### Configuration

```php
[
    'tenant_id' => '01abc123...',
    'name' => 'valid_phone_ph',
    'label' => 'Valid Philippine Phone Number',
    'type' => 'regex',
    'config' => [
        'pattern' => '/^(\+63|0)?9\d{9}$/',
        'message' => 'Must be a valid PH phone number',
    ],
    'is_active' => true,
]
```

#### Use Cases

- Phone number formats
- Email domain restrictions
- Employee ID patterns
- Postal codes
- Color codes (hex, RGB)
- License plate numbers
- Product SKU formats

#### Examples

```php
// Philippine phone numbers
'pattern' => '/^(\+63|0)?9\d{9}$/'
// Matches: +639171234567, 09171234567, 9171234567

// Employee ID (EMP-123456)
'pattern' => '/^EMP-\d{6}$/'

// Strong password
'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'

// Hex color code
'pattern' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
```

---

### Expression Rules (Phase 2)

**Status**: ✅ Implemented

Multi-field validation using Symfony Expression Language. Best for complex business logic.

#### Configuration

```php
[
    'tenant_id' => '01abc123...',
    'name' => 'engineering_salary_minimum',
    'label' => 'Engineering Minimum Salary',
    'type' => 'expression',
    'config' => [
        'expression' => 'department != "ENGINEERING" or salary >= 50000',
        'message' => 'Engineering employees must earn >= ₱50,000',
    ],
    'is_active' => true,
]
```

#### Use Cases

- Salary ranges based on department
- Age-based eligibility rules
- Conditional required fields
- Price calculations
- Shipping eligibility
- Discount validation
- Multi-field business rules

#### Supported Operations

| Category | Operators | Example |
|----------|-----------|---------|
| **Comparison** | `==`, `!=`, `<`, `>`, `<=`, `>=` | `age >= 18` |
| **Logical** | `and`, `or`, `not` | `age >= 18 and age <= 65` |
| **Membership** | `in` | `country in ["PH", "US"]` |
| **Math** | `+`, `-`, `*`, `/`, `%`, `**` | `(price * quantity) > 1000` |
| **String** | `~` (concat), `matches` | `name matches "/^[A-Z]/"` |

#### Context Variables

Expression rules have access to ALL fields in the row:

```php
// Available in expression:
$context = [
    'value' => 75000,              // Current field value
    'first_name' => 'John',        // All other fields
    'last_name' => 'Doe',
    'email' => 'john@company.com',
    'department' => 'ENGINEERING',
    'salary' => 75000,
    'hire_date' => '2023-01-15',
    // ... any CSV column
];
```

#### Examples

```php
// Salary range
'salary >= 30000 and salary <= 200000'

// Department-specific salary
'department != "ENGINEERING" or salary >= 50000'

// Recent hire validation
'hire_date < "2023-01-01" or salary >= 40000'

// Discount eligibility
'age >= 60 or age <= 12 or (is_student and age <= 25)'

// Order minimum
'total_amount >= 1000 or (is_member and total_amount >= 500)'

// Shipping rules
'weight <= 50 and country in ["PH", "US", "CA"]'

// Complex conditional
'(department in ["Engineering", "Sales"] and salary >= 50000) or years_of_service >= 10'
```

---

### Callback Rules (Phase 3)

**Status**: 🚧 Planned (Not Implemented)

Custom PHP code execution in sandboxed environment. For advanced users only.

#### Future Configuration

```php
[
    'type' => 'callback',
    'config' => [
        'code' => 'return strlen($value) === 10 && ctype_digit($value);',
        'message' => 'Must be exactly 10 digits',
        'sandbox' => true,
    ],
]
```

#### Security Concerns

- Requires sandboxed execution (Docker/ProcessPool)
- Whitelist/blacklist functions
- Execution timeout limits
- Rate limiting per tenant
- Audit trail for all executions

---

## Database Schema

### `custom_validation_rules` Table

Located in **central database** (tenant-specific via `tenant_id`).

```sql
CREATE TABLE custom_validation_rules (
    id                UUID PRIMARY KEY,
    tenant_id         UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name              VARCHAR(255) NOT NULL,
    label             VARCHAR(255) NOT NULL,
    description       TEXT NULL,
    type              VARCHAR(50) NOT NULL DEFAULT 'regex',
    config            JSON NOT NULL,
    is_active         BOOLEAN NOT NULL DEFAULT true,
    created_at        TIMESTAMP NOT NULL,
    updated_at        TIMESTAMP NOT NULL,
    
    UNIQUE (tenant_id, name),
    INDEX (tenant_id, is_active)
);
```

#### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `id` | ULID | Primary key |
| `tenant_id` | ULID | Foreign key to tenants table |
| `name` | String | Unique rule identifier (e.g., `valid_phone_ph`) |
| `label` | String | Human-readable name |
| `description` | Text | Optional description for documentation |
| `type` | String | `'regex'`, `'expression'`, or `'callback'` |
| `config` | JSON | Type-specific configuration (pattern, expression, etc.) |
| `is_active` | Boolean | Enable/disable rule without deleting |
| `created_at` | Timestamp | Creation timestamp |
| `updated_at` | Timestamp | Last update timestamp |

#### Config JSON Structure

**Regex:**
```json
{
    "pattern": "/^(\\+63|0)?9\\d{9}$/",
    "message": "Must be a valid PH phone number"
}
```

**Expression:**
```json
{
    "expression": "salary >= 30000 and salary <= 200000",
    "message": "Salary must be between ₱30,000 and ₱200,000"
}
```

---

## Creating Custom Rules

### Via Seeder (Development)

```php
// database/seeders/CustomValidationRuleSeeder.php
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'valid_email_domain',
    'label' => 'Valid Company Email Domain',
    'description' => 'Email must be from @company.com domain',
    'type' => 'regex',
    'config' => [
        'pattern' => '/^[a-z0-9._%+-]+@company\.com$/i',
        'message' => 'Email must be from @company.com',
    ],
    'is_active' => true,
]);
```

### Via Eloquent (Programmatic)

```php
use App\Models\CustomValidationRule;

$rule = CustomValidationRule::create([
    'tenant_id' => auth()->user()->tenant_id,
    'name' => 'senior_discount_eligible',
    'label' => 'Senior Citizen Discount Eligibility',
    'type' => 'expression',
    'config' => [
        'expression' => 'age >= 60 and country == "PH"',
        'message' => 'Must be 60+ and in Philippines for senior discount',
    ],
    'is_active' => true,
]);
```

### Via API (Future UI)

```http
POST /api/validation-rules
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "valid_product_sku",
    "label": "Valid Product SKU",
    "description": "SKU format: ABC-12345",
    "type": "regex",
    "config": {
        "pattern": "/^[A-Z]{3}-\\d{5}$/",
        "message": "SKU must be in format ABC-12345"
    }
}
```

### Testing Rules

Use the `test()` method to validate sample data:

```php
$rule = CustomValidationRule::where('name', 'valid_phone_ph')->first();

$result = $rule->test('+639171234567');
// ['valid' => true, 'message' => 'Valid', 'rule_name' => 'valid_phone_ph', 'rule_type' => 'regex']

$result = $rule->test('12345');
// ['valid' => false, 'message' => 'Must be a valid PH phone number', ...]
```

---

## Using Custom Rules

### In Campaign Configuration

Add to `validation_rules` array in campaign config:

```php
// database/seeders/CampaignSeeder.php
'pipeline_config' => [
    'processors' => [
        ['id' => $processors['csv-importer'], 'type' => 'ocr', 'config' => [
            'filters' => [
                'validation_rules' => [
                    // Laravel built-in rules
                    'email' => ['required', 'email'],
                    
                    // Custom regex rule
                    'phone' => ['required', 'custom:valid_phone_ph'],
                    
                    // Custom expression rule (multi-field)
                    'salary' => [
                        'required',
                        'numeric',
                        'custom:engineering_salary_minimum',
                    ],
                ],
            ],
        ]],
    ],
],
```

### Syntax

```php
'custom:rule_name'
```

Where `rule_name` matches the `name` field in the `custom_validation_rules` table.

### Combining with Laravel Rules

Custom rules work alongside Laravel's built-in validation:

```php
'email' => [
    'required',          // Laravel
    'email:rfc,dns',     // Laravel
    'custom:company_email_domain',  // Custom regex
],

'salary' => [
    'required',          // Laravel
    'numeric',           // Laravel
    'min:0',             // Laravel
    'max:999999',        // Laravel
    'custom:valid_salary_range',    // Custom expression
    'custom:engineering_salary_minimum',  // Custom expression
],
```

### Execution Order

1. **Transformations** (trim, uppercase, type conversion)
2. **Date conversions**
3. **Custom expression rules** (with full row context)
4. **Laravel validation** (including custom regex rules)

---

## Expression Language Reference

### Syntax Overview

```php
// Comparisons
'age >= 18'
'status == "active"'
'price < 1000'

// Logical operators
'age >= 18 and age <= 65'
'country == "PH" or country == "US"'
'not is_banned'

// Math operations
'(price * quantity) > 1000'
'discount_percentage <= 50'
'total == subtotal + tax'

// String operations
'email matches "/^[a-z]+@company\\.com$/"'
'first_name ~ " " ~ last_name'  // Concatenation

// Array membership
'department in ["Engineering", "Sales", "Marketing"]'
'status not in ["banned", "suspended"]'
```

### Data Types

| Type | Example | Notes |
|------|---------|-------|
| **String** | `"ENGINEERING"` | Double quotes |
| **Number** | `50000`, `3.14` | Integer or float |
| **Boolean** | `true`, `false` | Lowercase |
| **Array** | `["A", "B", "C"]` | For `in` operator |
| **Null** | `null` | Lowercase |

### Comparison Operators

```php
==    // Equal
!=    // Not equal
<     // Less than
>     // Greater than
<=    // Less than or equal
>=    // Greater than or equal
```

### Logical Operators

```php
and   // Logical AND
or    // Logical OR
not   // Logical NOT

// Precedence (highest to lowest):
// 1. not
// 2. and
// 3. or

// Use parentheses to control precedence:
'(age >= 18 and age <= 60) or is_admin'
```

### Mathematical Operators

```php
+     // Addition
-     // Subtraction
*     // Multiplication
/     // Division
%     // Modulo
**    // Power

// Example:
'(price * quantity * (1 - discount / 100)) >= 1000'
```

### String Operations

```php
~     // Concatenation
'first_name ~ " " ~ last_name'
// Result: "John Doe"

matches   // Regex matching
'email matches "/^[a-z]+@/"'
```

### Membership Testing

```php
in        // Value in array
'country in ["PH", "US", "CA"]'

not in    // Value not in array
'status not in ["banned", "deleted"]'
```

### Common Patterns

**Age range:**
```php
'age >= 18 and age <= 65'
```

**Conditional requirement:**
```php
'country != "PH" or has_tin == true'
// "If in Philippines, must have TIN"
```

**Either/or logic:**
```php
'has_license or has_permit'
```

**Complex eligibility:**
```php
'(age >= 60 or age <= 12) and country == "PH"'
// "Senior or child, and in Philippines"
```

**Department-specific rules:**
```php
'department != "ENGINEERING" or (salary >= 50000 and years_of_service >= 2)'
// "If Engineering, must earn 50k+ with 2+ years experience"
```

**Price calculation:**
```php
'(price * quantity) >= 1000 or is_vip_member == true'
```

---

## Examples

### Example 1: Phone Number Validation

**Requirement**: Validate Philippine mobile numbers in multiple formats.

```php
// Create rule
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'valid_phone_ph',
    'label' => 'Valid Philippine Phone Number',
    'type' => 'regex',
    'config' => [
        'pattern' => '/^(\+63|0)?9\d{9}$/',
        'message' => 'Must be a valid PH phone number (e.g., +639171234567)',
    ],
]);

// Use in campaign
'phone' => ['required', 'custom:valid_phone_ph']

// Validates:
// ✅ +639171234567
// ✅ 09171234567
// ✅ 9171234567
// ❌ 12345
// ❌ 8171234567 (landline)
```

### Example 2: Salary Range by Department

**Requirement**: Engineering employees must earn at least ₱50,000.

```php
// Create rule
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'engineering_salary_minimum',
    'label' => 'Engineering Minimum Salary',
    'type' => 'expression',
    'config' => [
        'expression' => 'department != "ENGINEERING" or salary >= 50000',
        'message' => 'Engineering employees must earn >= ₱50,000',
    ],
]);

// Use in campaign
'salary' => [
    'required',
    'numeric',
    'custom:engineering_salary_minimum',
]

// Test with CSV data:
// ✅ Engineering, 75000 → Pass
// ✅ Marketing, 35000 → Pass (not Engineering)
// ❌ Engineering, 35000 → Fail (Engineering but < 50k)
```

### Example 3: Recent Hire Salary Requirements

**Requirement**: Employees hired after 2023 must earn at least ₱40,000.

```php
// Create rule
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'recent_hire_minimum',
    'label' => 'Recent Hire Minimum Salary',
    'type' => 'expression',
    'config' => [
        'expression' => 'hire_date < "2023-01-01" or salary >= 40000',
        'message' => 'Employees hired in 2023+ must earn >= ₱40,000',
    ],
]);

// Use in campaign
'salary' => [
    'required',
    'numeric',
    'custom:recent_hire_minimum',
]

// Test with CSV data:
// ✅ 2022-12-15, 35000 → Pass (hired before 2023)
// ✅ 2023-06-01, 45000 → Pass (hired after 2023, salary OK)
// ❌ 2023-06-01, 35000 → Fail (hired after 2023, salary too low)
```

### Example 4: Senior Citizen Discount Eligibility

**Requirement**: Seniors (60+) in Philippines get discounts.

```php
// Create rule
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'senior_discount_eligible',
    'label' => 'Senior Discount Eligibility',
    'type' => 'expression',
    'config' => [
        'expression' => 'age >= 60 and country == "PH"',
        'message' => 'Must be 60+ years old and in Philippines',
    ],
]);

// Use in form/API validation
'age' => ['required', 'numeric', 'custom:senior_discount_eligible']

// Test:
// ✅ 65, PH → Pass
// ❌ 55, PH → Fail (not 60+)
// ❌ 65, US → Fail (not in PH)
```

### Example 5: Complex Order Validation

**Requirement**: Orders must be ≥₱1,000 OR customer must be a VIP member.

```php
// Create rule
CustomValidationRule::create([
    'tenant_id' => $tenant->id,
    'name' => 'order_minimum_or_vip',
    'label' => 'Order Minimum or VIP',
    'type' => 'expression',
    'config' => [
        'expression' => 'total_amount >= 1000 or is_vip == true',
        'message' => 'Order must be >=₱1,000 or customer must be VIP',
    ],
]);

// Use in campaign
'total_amount' => [
    'required',
    'numeric',
    'custom:order_minimum_or_vip',
]

// Test:
// ✅ 1500, false → Pass (total >= 1000)
// ✅ 500, true → Pass (VIP member)
// ❌ 500, false → Fail (total < 1000 and not VIP)
```

---

## API Reference

### CustomValidationRule Model

#### Methods

**`validate(mixed $value, array $context = []): bool`**

Validates a value against the rule.

```php
$rule = CustomValidationRule::find($id);

// Regex rule (single field)
$isValid = $rule->validate('+639171234567');

// Expression rule (with full row context)
$isValid = $rule->validate(75000, [
    'department' => 'ENGINEERING',
    'salary' => 75000,
    'hire_date' => '2023-01-15',
]);
```

**`test(mixed $value, array $context = []): array`**

Tests a rule and returns detailed result.

```php
$result = $rule->test('+639171234567');
// [
//     'valid' => true,
//     'message' => 'Valid',
//     'rule_name' => 'valid_phone_ph',
//     'rule_type' => 'regex',
// ]
```

**`getErrorMessage(): string`**

Gets the error message from config.

```php
$message = $rule->getErrorMessage();
// "Must be a valid PH phone number"
```

#### Relationships

**`tenant(): BelongsTo`**

Gets the tenant that owns the rule.

```php
$tenant = $rule->tenant;
```

#### Scopes

```php
// Active rules only
CustomValidationRule::where('is_active', true)->get();

// By tenant
CustomValidationRule::where('tenant_id', $tenantId)->get();

// By type
CustomValidationRule::where('type', 'expression')->get();
```

---

### CustomRuleRegistry Service

Static registry for loading and caching rules.

#### Methods

**`CustomRuleRegistry::loadTenantRules(string $tenantId): void`**

Loads all active rules for a tenant.

```php
CustomRuleRegistry::loadTenantRules($tenantId);
```

**`CustomRuleRegistry::get(string $name): ?CustomValidationRule`**

Gets a rule by name.

```php
$rule = CustomRuleRegistry::get('valid_phone_ph');
```

**`CustomRuleRegistry::has(string $name): bool`**

Checks if a rule exists.

```php
if (CustomRuleRegistry::has('valid_phone_ph')) {
    // Rule exists
}
```

**`CustomRuleRegistry::all(): array`**

Gets all loaded rules.

```php
$rules = CustomRuleRegistry::all();
```

**`CustomRuleRegistry::clear(): void`**

Clears in-memory cache.

```php
CustomRuleRegistry::clear();
```

**`CustomRuleRegistry::clearCache(string $tenantId): void`**

Clears Redis cache for a tenant.

```php
CustomRuleRegistry::clearCache($tenantId);
```

**`CustomRuleRegistry::refresh(): void`**

Refreshes rules for current tenant.

```php
CustomRuleRegistry::refresh();
```

---

## Performance & Caching

### Caching Strategy

```
Request Start
    ↓
Load from Redis (1 hour TTL)
    ↓
If miss: Query database → Store in Redis
    ↓
Store in memory (for current request)
    ↓
Validate 1000s of rows (no additional DB queries)
    ↓
Request End (memory cleared)
```

### Cache Keys

```php
"custom_validation_rules:{tenant_id}"
```

### Cache Duration

- **Redis**: 1 hour (configurable in `CustomRuleRegistry`)
- **In-memory**: Request lifetime only

### Performance Characteristics

| Operation | Time | Notes |
|-----------|------|-------|
| **First load** | ~50ms | Database query + Redis write |
| **Subsequent loads** | ~5ms | Redis read |
| **In-memory access** | <1ms | Array lookup |
| **Regex validation** | ~0.1ms | Per row |
| **Expression validation** | ~0.5ms | Per row (compiled) |

### Optimization Tips

1. **Pre-load rules**: Call `CustomRuleRegistry::loadTenantRules()` early
2. **Use regex for simple patterns**: Faster than expressions
3. **Cache compiled expressions**: Symfony Expression Language auto-compiles
4. **Limit rule complexity**: Simpler expressions = faster validation
5. **Monitor cache hit rate**: Should be >95%

### Invalidating Cache

```php
// After creating/updating rules
CustomRuleRegistry::clearCache($tenantId);

// Or refresh current tenant
CustomRuleRegistry::refresh();
```

---

## Security Considerations

### Regex Rules

**Threats:**
- ReDoS (Regular Expression Denial of Service)
- Excessive backtracking

**Mitigations:**
- Try/catch blocks around `preg_match()`
- Log errors for invalid patterns
- Consider timeout limits for complex patterns

**Best Practices:**
```php
// ✅ Good - Simple, specific
'/^\d{4}$/'

// ❌ Bad - Vulnerable to ReDoS
'/^(a+)+$/'

// ❌ Bad - Too complex
'/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])(?=.*[^a-zA-Z\d@$!%*?&]).{8,}$/'
```

### Expression Rules

**Threats:**
- Information disclosure (accessing sensitive fields)
- Resource exhaustion (complex expressions)

**Mitigations:**
- Symfony Expression Language is sandboxed (no arbitrary code execution)
- No file system or network access
- Try/catch blocks around evaluation
- Log evaluation errors

**Safe:**
```php
// ✅ No security risk
'salary >= 50000 and department == "ENGINEERING"'
```

**Not Possible (Expression Language restrictions):**
```php
// ❌ Cannot access file system
'file_get_contents("/etc/passwd")'

// ❌ Cannot execute shell commands
'exec("rm -rf /")'

// ❌ Cannot access globals/superglobals
'$_SERVER["HTTP_HOST"]'
```

### Callback Rules (Future)

**Threats:**
- Arbitrary code execution
- File system access
- Network access
- Database access

**Required Mitigations:**
- Docker container sandbox
- Function whitelist/blacklist
- Execution timeout (5 seconds)
- Rate limiting (100 executions/minute per tenant)
- Audit trail for all executions
- Admin approval for new callback rules

---

## Troubleshooting

### Common Issues

#### 1. Rule Not Found

**Error**: `Custom validation rule not found`

**Cause**: Rule name doesn't exist or tenant mismatch

**Solution**:
```php
// Check rule exists
$rule = CustomValidationRule::where('tenant_id', $tenantId)
    ->where('name', 'valid_phone_ph')
    ->first();

// Check cache
CustomRuleRegistry::loadTenantRules($tenantId);
$exists = CustomRuleRegistry::has('valid_phone_ph');
```

#### 2. Expression Syntax Error

**Error**: `Invalid expression in custom validation rule`

**Cause**: Syntax error in expression

**Solution**:
```php
// Check logs
tail -f storage/logs/laravel.log | grep "Invalid expression"

// Test expression manually
$language = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();
try {
    $result = $language->evaluate('salary >= 50000', ['salary' => 75000]);
} catch (\Exception $e) {
    echo $e->getMessage();
}
```

#### 3. Regex Validation Fails Unexpectedly

**Error**: Rule always returns false

**Cause**: Pattern error or invalid regex

**Solution**:
```php
// Test regex in PHP
$pattern = '/^(\+63|0)?9\d{9}$/';
$value = '+639171234567';

if (preg_match($pattern, $value) === 1) {
    echo "Match!";
} else {
    echo "No match";
}

// Check for errors
if (preg_last_error() !== PREG_NO_ERROR) {
    echo "Regex error: " . preg_last_error();
}
```

#### 4. Cache Not Refreshing

**Error**: Rule changes not taking effect

**Cause**: Cache not cleared after update

**Solution**:
```php
// Clear Redis cache
CustomRuleRegistry::clearCache($tenantId);

// Or via CLI
php artisan cache:forget "custom_validation_rules:{tenant_id}"
```

#### 5. Expression Rule Not Accessing Row Context

**Error**: Expression rule can't see other fields

**Cause**: Using wrong field name or field not in row

**Solution**:
```php
// Debug: Log row context
\Log::debug('Row context', $row);

// Check field names match exactly (case-sensitive after transformation)
// CSV: "department" → After transformation: "DEPARTMENT" or "department"?

// Use exact field names from transformed row
'department != "ENGINEERING"'  // If transformed to uppercase
// not
'Department != "ENGINEERING"'  // Wrong case
```

### Debugging Tips

**Enable debug logging:**
```php
// In CsvImportProcessor
\Log::debug('Custom rule validation', [
    'rule_name' => $customRule->name,
    'field' => $field,
    'value' => $fieldValue,
    'context' => $row,
    'result' => $isValid,
]);
```

**Test rules in isolation:**
```php
// Tinker
php artisan tinker

$rule = CustomValidationRule::where('name', 'valid_phone_ph')->first();
$rule->test('+639171234567');
```

**Check tenant context:**
```php
// Ensure tenant is initialized
$tenant = \App\Tenancy\TenantContext::current();
if (!$tenant) {
    throw new \RuntimeException('No tenant context');
}
```

---

## Future Enhancements

### Phase 3: Callback Rules

- Sandboxed PHP code execution
- Function whitelist
- Execution timeout
- Rate limiting
- Audit trail

### UI Improvements

- Visual rule builder
- Drag-and-drop expression creator
- Real-time validation testing
- Rule templates library
- Import/export rules

### Advanced Features

- **Rule versioning**: Track rule changes over time
- **Rule dependencies**: Rules that depend on other rules
- **Conditional rules**: Apply rules based on campaign type
- **Rule groups**: Bundle related rules together
- **Performance monitoring**: Track rule execution time
- **A/B testing**: Test rule variations

---

## Related Documentation

- [CSV Import Documentation](./CSV_IMPORT.md)
- [Laravel Validation](https://laravel.com/docs/validation)
- [Symfony Expression Language](https://symfony.com/doc/current/components/expression_language.html)
- [Multi-Tenancy Architecture](./MULTI_TENANCY.md)

---

## Support

For questions or issues with custom validation rules:

1. Check this documentation
2. Review logs: `storage/logs/laravel.log`
3. Test rules in isolation using `test()` method
4. Contact platform support

---

**Last Updated**: December 3, 2024  
**Version**: 1.0 (Phase 1 + Phase 2 complete)
