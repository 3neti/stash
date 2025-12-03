<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * CustomValidationRule
 *
 * User-defined validation rules for flexible data validation.
 * Supports regex patterns (Phase 1), with expression and callback types planned.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property string $type ('regex', 'expression', 'callback')
 * @property array $config
 * @property bool $is_active
 */
class CustomValidationRule extends Model
{
    use HasUlids;

    /**
     * Connection name for central database.
     */
    protected $connection = 'central';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'label',
        'description',
        'type',
        'config',
        'translations',
        'placeholders',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'config' => 'array',
        'translations' => 'array',
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns this custom rule.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Validate a value against this custom rule.
     *
     * @param  mixed  $value  The value to validate
     * @param  array  $context  Full row context for expression validation
     * @return bool True if valid, false otherwise
     */
    public function validate(mixed $value, array $context = []): bool
    {
        if (! $this->is_active) {
            return true; // Inactive rules always pass
        }

        return match ($this->type) {
            'regex' => $this->validateRegex($value),
            'expression' => $this->validateExpression($value, $context),
            'callback' => $this->validateCallback($value),
            default => true,
        };
    }

    /**
     * Validate using regex pattern.
     */
    protected function validateRegex(mixed $value): bool
    {
        $pattern = $this->config['pattern'] ?? null;

        if (! $pattern) {
            return true; // No pattern = always valid
        }

        // Ensure value is string
        $stringValue = (string) $value;

        try {
            return preg_match($pattern, $stringValue) === 1;
        } catch (\Exception $e) {
            // Invalid regex pattern - log error and fail validation
            \Log::error('Invalid regex pattern in custom validation rule', [
                'rule_id' => $this->id,
                'rule_name' => $this->name,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate using Symfony Expression Language.
     *
     * Supports complex multi-field logic like:
     * - 'age >= 18 and age <= 65'
     * - 'salary > 50000 or department in ["Engineering", "Sales"]'
     * - '(price * quantity) > 1000'
     */
    protected function validateExpression(mixed $value, array $context = []): bool
    {
        $expression = $this->config['expression'] ?? null;

        if (! $expression) {
            return true; // No expression = always valid
        }

        try {
            $language = new ExpressionLanguage();

            // Build evaluation context
            // 'value' is the current field value
            // All other row fields are available by name
            $evalContext = ['value' => $value] + $context;

            // Evaluate expression
            $result = $language->evaluate($expression, $evalContext);

            return (bool) $result;
        } catch (\Exception $e) {
            // Invalid expression - log error and fail validation
            \Log::error('Invalid expression in custom validation rule', [
                'rule_id' => $this->id,
                'rule_name' => $this->name,
                'expression' => $expression,
                'context' => $evalContext ?? [],
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate using PHP callback (Phase 3 - Future).
     */
    protected function validateCallback(mixed $value): bool
    {
        // TODO: Implement in Phase 3 with sandbox execution
        return true;
    }

    /**
     * Get the error message for this rule with locale support.
     *
     * Lookup order:
     * 1. translations[locale]
     * 2. config['message'] (default)
     * 3. Hardcoded fallback
     *
     * @param  string|null  $locale  Locale code (e.g., 'en', 'fil', 'es')
     * @param  array  $context  Context for placeholder replacement (field name, value, etc.)
     * @return string Localized error message with placeholders replaced
     */
    public function getErrorMessage(?string $locale = null, array $context = []): string
    {
        // Use app locale if not specified
        $locale = $locale ?? app()->getLocale();

        // 1. Try to get translation for the specified locale
        $message = null;
        if ($this->translations && isset($this->translations[$locale])) {
            $message = $this->translations[$locale];
        }

        // 2. Fallback to default message in config
        if (! $message) {
            $message = $this->config['message'] ?? "The value does not match the required format.";
        }

        // 3. Replace placeholders
        return $this->replacePlaceholders($message, $context, $locale);
    }

    /**
     * Replace placeholders in the message with localized values.
     *
     * Supports:
     * - :attribute - Field name
     * - :value - Current value
     * - :code, :date, etc. - Custom placeholders from config
     *
     * @param  string  $message  Message with placeholders
     * @param  array  $context  Context data (field name, value, etc.)
     * @param  string  $locale  Locale for placeholder values
     * @return string Message with placeholders replaced
     */
    protected function replacePlaceholders(string $message, array $context, string $locale): string
    {
        // Standard placeholders
        if (isset($context['attribute'])) {
            $message = str_replace(':attribute', $context['attribute'], $message);
        }
        if (isset($context['value'])) {
            $message = str_replace(':value', (string) $context['value'], $message);
        }

        // Custom placeholders from config
        if ($this->placeholders) {
            foreach ($this->placeholders as $key => $values) {
                // Get localized value for this placeholder
                $value = $values[$locale] ?? $values['en'] ?? $key;
                $message = str_replace(":{$key}", $value, $message);
            }
        }

        return $message;
    }

    /**
     * Test the rule with a sample value (useful for UI).
     *
     * @param  mixed  $value  Value to test
     * @param  array  $context  Context for expression validation and placeholder replacement
     * @param  string|null  $locale  Locale for error message
     * @return array Test result with localized message
     */
    public function test(mixed $value, array $context = [], ?string $locale = null): array
    {
        $isValid = $this->validate($value, $context);

        return [
            'valid' => $isValid,
            'message' => $isValid ? 'Valid' : $this->getErrorMessage($locale, $context),
            'rule_name' => $this->name,
            'rule_type' => $this->type,
        ];
    }
}
