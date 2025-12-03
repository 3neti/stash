<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Models\CustomValidationRule;
use Illuminate\Support\Facades\Cache;

/**
 * CustomRuleRegistry
 *
 * Registry pattern for loading and caching tenant-specific custom validation rules.
 * Provides efficient access to rules during CSV import and other validation scenarios.
 */
class CustomRuleRegistry
{
    /**
     * In-memory cache of loaded rules for current request.
     *
     * @var array<string, CustomValidationRule>
     */
    protected static array $rules = [];

    /**
     * Currently loaded tenant ID.
     */
    protected static ?string $loadedTenantId = null;

    /**
     * Register a custom rule in the registry.
     */
    public static function register(string $name, CustomValidationRule $rule): void
    {
        self::$rules[$name] = $rule;
    }

    /**
     * Get a custom rule by name.
     */
    public static function get(string $name): ?CustomValidationRule
    {
        return self::$rules[$name] ?? null;
    }

    /**
     * Check if a rule exists in the registry.
     */
    public static function has(string $name): bool
    {
        return isset(self::$rules[$name]);
    }

    /**
     * Load all active custom rules for a tenant.
     *
     * Uses Laravel cache to avoid repeated database queries.
     */
    public static function loadTenantRules(string $tenantId): void
    {
        // Skip if already loaded for this tenant
        if (self::$loadedTenantId === $tenantId) {
            return;
        }

        // Clear existing rules
        self::clear();

        // Load from cache or database
        $cacheKey = "custom_validation_rules:{$tenantId}";
        $cacheDuration = 3600; // 1 hour

        $rules = Cache::remember($cacheKey, $cacheDuration, function () use ($tenantId) {
            return CustomValidationRule::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();
        });

        // Register each rule
        foreach ($rules as $rule) {
            self::register($rule->name, $rule);
        }

        self::$loadedTenantId = $tenantId;
    }

    /**
     * Clear all loaded rules from the registry.
     */
    public static function clear(): void
    {
        self::$rules = [];
        self::$loadedTenantId = null;
    }

    /**
     * Get all loaded rules.
     *
     * @return array<string, CustomValidationRule>
     */
    public static function all(): array
    {
        return self::$rules;
    }

    /**
     * Clear cache for a specific tenant.
     */
    public static function clearCache(string $tenantId): void
    {
        $cacheKey = "custom_validation_rules:{$tenantId}";
        Cache::forget($cacheKey);

        // If this tenant is currently loaded, clear the in-memory cache too
        if (self::$loadedTenantId === $tenantId) {
            self::clear();
        }
    }

    /**
     * Refresh rules for the currently loaded tenant.
     */
    public static function refresh(): void
    {
        if (self::$loadedTenantId) {
            self::clearCache(self::$loadedTenantId);
            self::loadTenantRules(self::$loadedTenantId);
        }
    }
}
