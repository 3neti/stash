<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant Tier Enum
 * 
 * Represents the subscription tier/plan of a tenant.
 */
enum TenantTier: string
{
    case Starter = 'starter';
    case Professional = 'professional';
    case Enterprise = 'enterprise';
    
    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match($this) {
            self::Starter => 'Starter',
            self::Professional => 'Professional',
            self::Enterprise => 'Enterprise',
        };
    }
    
    /**
     * Get monthly document limit.
     */
    public function documentLimit(): int
    {
        return match($this) {
            self::Starter => 1000,
            self::Professional => 10000,
            self::Enterprise => 100000,
        };
    }
    
    /**
     * Get monthly AI token limit.
     */
    public function aiTokenLimit(): int
    {
        return match($this) {
            self::Starter => 100000,
            self::Professional => 1000000,
            self::Enterprise => 10000000,
        };
    }
    
    /**
     * Check if tier has feature access.
     */
    public function hasFeature(string $feature): bool
    {
        return match($feature) {
            'custom_processors' => $this !== self::Starter,
            'webhooks' => $this !== self::Starter,
            'priority_support' => $this === self::Enterprise,
            'sla_guarantee' => $this === self::Enterprise,
            'dedicated_resources' => $this === self::Enterprise,
            default => true,
        };
    }
    
    /**
     * Get all enum values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
