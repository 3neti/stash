<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'config' => 'array',
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
     * @return bool True if valid, false otherwise
     */
    public function validate(mixed $value): bool
    {
        if (! $this->is_active) {
            return true; // Inactive rules always pass
        }

        return match ($this->type) {
            'regex' => $this->validateRegex($value),
            'expression' => $this->validateExpression($value),
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
     * Validate using expression language (Phase 2 - Future).
     */
    protected function validateExpression(mixed $value): bool
    {
        // TODO: Implement in Phase 2 using Symfony Expression Language
        return true;
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
     * Get the error message for this rule.
     */
    public function getErrorMessage(): string
    {
        return $this->config['message'] ?? "The value does not match the required format.";
    }

    /**
     * Test the rule with a sample value (useful for UI).
     */
    public function test(mixed $value): array
    {
        $isValid = $this->validate($value);

        return [
            'valid' => $isValid,
            'message' => $isValid ? 'Valid' : $this->getErrorMessage(),
            'rule_name' => $this->name,
            'rule_type' => $this->type,
        ];
    }
}
