# Multi-Language Translations Guide

This guide explains how to add translations for custom validation rules in the Stash/DeadDrop platform.

## Table of Contents

- [Overview](#overview)
- [Supported Locales](#supported-locales)
- [Adding a New Locale](#adding-a-new-locale)
- [Translating Custom Validation Rules](#translating-custom-validation-rules)
- [Custom Placeholders](#custom-placeholders)
- [Setting Locale for Campaigns](#setting-locale-for-campaigns)
- [Setting Locale for Tenants](#setting-locale-for-tenants)
- [Testing Translations](#testing-translations)

## Overview

The Stash platform supports multi-language validation error messages for CSV import campaigns. Validation error messages can be translated into any language, with support for dynamic placeholders and currency symbols.

### Locale Detection Priority

When validating CSV data, the system detects the locale in this order:

1. **Campaign `settings['locale']`** (highest priority)
2. **Tenant `settings['locale']`** (fallback)
3. **Default: `'en'`** (English)

### Architecture

```
CsvImportProcessor
  ↓ detectLocale()
  ↓ validates row with locale
  ↓
CustomValidationRule
  ↓ getErrorMessage($locale, $context)
  ↓ replacePlaceholders()
  ↓
Localized Error Message
```

## Supported Locales

Currently supported locales:

| Locale Code | Language | Currency Symbol |
|-------------|----------|-----------------|
| `en` | English | $ (Dollar) |
| `fil` | Filipino (Tagalog) | ₱ (Peso) |
| `es` | Spanish | $ (Dollar) |

**Want to add more locales?** See [Adding a New Locale](#adding-a-new-locale) below.

## Adding a New Locale

To add support for a new language (e.g., French `fr`), follow these steps:

### Step 1: Update Existing Custom Validation Rules

For each existing custom validation rule, add translations for the new locale:

```php
use App\Models\CustomValidationRule;

$rule = CustomValidationRule::where('name', 'engineering_salary_minimum')->first();

// Add French translation
$translations = $rule->translations ?? [];
$translations['fr'] = 'Les employés de :department doivent avoir un salaire >= :currency:amount';
$rule->translations = $translations;

// Add French placeholders
$placeholders = $rule->placeholders ?? [];
$placeholders['department']['fr'] = 'Ingénierie';
$placeholders['currency']['fr'] = '€';
$placeholders['amount']['fr'] = '50 000';
$rule->placeholders = $placeholders;

$rule->save();
```

### Step 2: Update Seeder for New Rules

When creating new custom validation rules, include all supported locales:

```php
// database/seeders/CustomValidationRuleSeeder.php
CustomValidationRule::create([
    'name' => 'my_custom_rule',
    'type' => 'regex',
    'config' => [
        'pattern' => '/^[A-Z]{3}-\\d{3}$/',
        'message' => 'Must be format: AAA-123',
    ],
    'translations' => [
        'en' => ':attribute must match format: AAA-123',
        'fil' => ':attribute ay dapat tumugma sa format: AAA-123',
        'es' => ':attribute debe coincidir con el formato: AAA-123',
        'fr' => ':attribute doit correspondre au format: AAA-123',  // NEW
    ],
]);
```

### Step 3: Test the New Locale

Create a test campaign with the new locale:

```php
$campaign = Campaign::create([
    'name' => 'Employee Import (French)',
    'slug' => 'employee-import-fr',
    'settings' => ['locale' => 'fr'],  // Set locale
    'pipeline_config' => [
        'processors' => [/* ... */],
    ],
]);
```

Process a test CSV and verify error messages appear in French.

## Translating Custom Validation Rules

### Rule Types

There are three types of custom validation rules:

1. **Regex Rules** - Pattern-based validation (e.g., phone numbers, ZIP codes)
2. **Expression Rules** - Multi-field business logic (e.g., department salary minimum)
3. **Callback Rules** - Custom PHP logic (future phase)

### Translation Structure

Every custom validation rule has:

```php
[
    'name' => 'rule_name',              // Unique identifier
    'type' => 'regex|expression',       // Rule type
    'config' => [
        'pattern' => '...',              // For regex rules
        'expression' => '...',           // For expression rules
        'message' => 'Default message',  // Fallback message (EN)
    ],
    'translations' => [
        'en' => 'English message with :placeholders',
        'fil' => 'Filipino message with :placeholders',
        'es' => 'Spanish message with :placeholders',
    ],
    'placeholders' => [
        'placeholder_name' => [
            'en' => 'English value',
            'fil' => 'Filipino value',
            'es' => 'Spanish value',
        ],
    ],
]
```

### Example: Phone Number Validation

```php
CustomValidationRule::create([
    'name' => 'valid_phone_ph',
    'type' => 'regex',
    'config' => [
        'pattern' => '/^(\\+63|0)?9\\d{9}$/',
        'message' => 'Must be a valid Philippine phone number',
    ],
    'translations' => [
        'en' => ':attribute must be a valid :code phone number',
        'fil' => ':attribute ay dapat wastong numero ng telepono sa :code',
        'es' => ':attribute debe ser un número de teléfono válido de :code',
    ],
    'placeholders' => [
        'code' => [
            'en' => 'Philippine',
            'fil' => 'Pilipinas',
            'es' => 'Filipinas',
        ],
    ],
]);
```

**Result:**
- English: "Phone Number must be a valid Philippine phone number"
- Filipino: "Telepono ay dapat wastong numero ng telefono sa Pilipinas"
- Spanish: "Teléfono debe ser un número de teléfono válido de Filipinas"

### Example: Multi-Field Expression Rule

```php
CustomValidationRule::create([
    'name' => 'engineering_salary_minimum',
    'type' => 'expression',
    'config' => [
        'expression' => "row['department'] != 'ENGINEERING' or value >= 50000",
        'message' => 'Engineering employees must have salary >= $50,000',
    ],
    'translations' => [
        'en' => ':department employees must have salary >= :currency:amount',
        'fil' => 'Mga empleyado sa :department ay dapat may salary >= :currency:amount',
        'es' => 'Los empleados de :department deben tener salary >= :currency:amount',
    ],
    'placeholders' => [
        'department' => [
            'en' => 'Engineering',
            'fil' => 'Engineering',
            'es' => 'Ingeniería',
        ],
        'currency' => [
            'en' => '$',
            'fil' => '₱',
            'es' => '$',
        ],
        'amount' => [
            'en' => '50,000',
            'fil' => '50,000',
            'es' => '50.000',  // European number format
        ],
    ],
]);
```

**Result:**
- English: "Engineering employees must have salary >= $50,000"
- Filipino: "Mga empleyado sa Engineering ay dapat may salary >= ₱50,000"
- Spanish: "Los empleados de Ingeniería deben tener salary >= $50.000"

## Custom Placeholders

### Standard Placeholders

The system automatically provides these placeholders:

- `:attribute` - The field name being validated (e.g., "Phone Number", "Salary")
- `:value` - The actual value that failed validation

### Custom Placeholders

You can define your own placeholders for any additional context:

```php
'placeholders' => [
    'min_length' => [
        'en' => '8',
        'fil' => '8',
        'es' => '8',
    ],
    'max_length' => [
        'en' => '20',
        'fil' => '20',
        'es' => '20',
    ],
]
```

Then reference them in translations:

```php
'translations' => [
    'en' => ':attribute must be between :min_length and :max_length characters',
    'fil' => ':attribute ay dapat nasa pagitan ng :min_length at :max_length na character',
    'es' => ':attribute debe tener entre :min_length y :max_length caracteres',
]
```

### Currency Symbols

Use locale-specific currency symbols in placeholders:

```php
'currency' => [
    'en' => '$',      // US Dollar
    'fil' => '₱',     // Philippine Peso
    'es' => '$',      // Dollar (or '€' for Euro)
    'fr' => '€',      // Euro
    'jp' => '¥',      // Yen
]
```

### Number Formatting

Different locales use different number formats:

```php
'amount' => [
    'en' => '50,000',    // US format: comma separator
    'fil' => '50,000',   // Philippine format: comma separator
    'es' => '50.000',    // European format: dot separator
    'fr' => '50 000',    // French format: space separator
]
```

## Setting Locale for Campaigns

To set the locale for a specific campaign, add it to the campaign's `settings`:

### Via Seeder

```php
// database/seeders/CampaignSeeder.php
Campaign::create([
    'name' => 'Employee CSV Import (Filipino)',
    'slug' => 'employee-csv-import-fil',
    'settings' => [
        'queue' => 'default',
        'locale' => 'fil',  // Set locale here
    ],
    // ... other fields
]);
```

### Via Tinker

```php
use App\Models\Campaign;

$campaign = Campaign::find('01xyz...');
$campaign->settings = array_merge($campaign->settings ?? [], [
    'locale' => 'fil',
]);
$campaign->save();
```

### Via API/UI (Future)

```php
// Update campaign settings
PUT /api/campaigns/{id}
{
    "settings": {
        "locale": "fil"
    }
}
```

## Setting Locale for Tenants

To set a default locale for an entire tenant (affects all campaigns without a specific locale):

### Via Tinker

```php
use App\Models\Tenant;

$tenant = Tenant::on('central')->find('01xyz...');
$tenant->settings = array_merge($tenant->settings ?? [], [
    'locale' => 'es',
]);
$tenant->save();
```

### Via Tenant Management (Future)

```php
// Update tenant settings
PATCH /api/tenants/{id}
{
    "settings": {
        "locale": "es"
    }
}
```

## Testing Translations

### Manual Testing

Use the `document:process` command to test localized validation:

```bash
# Test Filipino locale
php artisan document:process /tmp/employees_fil.csv --campaign=employee-csv-import-fil --wait --show-output

# Test Spanish locale
php artisan document:process /tmp/employees_es.csv --campaign=employee-csv-import-es --wait --show-output
```

Check the logs for localized error messages:

```bash
tail -f storage/logs/laravel.log | grep "CSV row validation failed"
```

### Automated Testing

Run the feature tests for all locales:

```bash
# Test locale detection
php artisan test tests/Unit/Services/CsvImportProcessorLocaleTest.php

# Test localized validation messages
php artisan test tests/Feature/DeadDrop/CsvImportLocalizedValidationTest.php
```

### Testing via Tinker

```php
use App\Models\CustomValidationRule;
use App\Services\Validation\CustomRuleRegistry;

// Load rules
CustomRuleRegistry::loadTenantRules($tenantId);

// Get rule
$rule = CustomRuleRegistry::get('valid_phone_ph');

// Test error message in different locales
$context = ['attribute' => 'Phone Number', 'value' => 'invalid'];

echo "English: " . $rule->getErrorMessage('en', $context) . PHP_EOL;
echo "Filipino: " . $rule->getErrorMessage('fil', $context) . PHP_EOL;
echo "Spanish: " . $rule->getErrorMessage('es', $context) . PHP_EOL;
```

## Best Practices

### 1. Always Provide Fallback

Always include an English (`en`) translation as fallback:

```php
'translations' => [
    'en' => 'English message (fallback)',  // REQUIRED
    'fil' => 'Filipino message',
    'es' => 'Spanish message',
]
```

### 2. Keep Messages Concise

Validation error messages should be short and actionable:

✅ Good: "Salary must be >= $50,000"  
❌ Bad: "The salary field you entered does not meet the minimum requirement of $50,000 which is required for all employees in the Engineering department"

### 3. Use Consistent Terminology

Use the same terminology across all translations:

```php
// Good - consistent field names
'en' => ':attribute must be valid'
'fil' => ':attribute ay dapat wastong'

// Bad - inconsistent field names
'en' => 'Phone Number must be valid'
'fil' => 'Telepono ay dapat wastong'  // Should use :attribute
```

### 4. Test All Locales

Always test every translation you add:

```bash
# Create test CSVs for each locale
php artisan document:process /tmp/test_en.csv --campaign=test-import-en
php artisan document:process /tmp/test_fil.csv --campaign=test-import-fil
php artisan document:process /tmp/test_es.csv --campaign=test-import-es
```

### 5. Document Currency and Number Formats

When adding a new locale, document the expected currency and number formats:

```php
// French (France)
'currency' => 'EUR (€)',
'number_format' => '50 000',  // space separator
'decimal_format' => '123,45',  // comma for decimals

// Japanese
'currency' => 'JPY (¥)',
'number_format' => '50,000',  // comma separator
'decimal_format' => '123.45',  // dot for decimals
```

## Troubleshooting

### Error: "Translation not found"

**Problem**: Validation error shows in English despite setting a different locale.

**Solution**: Check that the custom validation rule has a translation for that locale:

```php
$rule = CustomValidationRule::where('name', 'my_rule')->first();
dd($rule->translations);  // Should include your locale
```

### Error: "Placeholder not replaced"

**Problem**: Error message shows `:placeholder` instead of the actual value.

**Solution**: Make sure the placeholder is defined for that locale:

```php
$rule->placeholders['my_placeholder']['fil'] = 'Filipino value';
$rule->save();
```

### Locale Not Detected

**Problem**: System uses English despite setting campaign locale.

**Solution**: Verify locale is set correctly:

```php
$campaign = Campaign::find('...');
dd($campaign->settings['locale']);  // Should be 'fil', 'es', etc.
```

Priority: Campaign > Tenant > Default (`en`)

---

## Need Help?

- **Documentation**: See `WARP.md` for system architecture
- **Examples**: Check `database/seeders/CustomValidationRuleSeeder.php`
- **Tests**: Review `tests/Feature/DeadDrop/CsvImportLocalizedValidationTest.php`
