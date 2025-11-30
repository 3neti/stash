<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant Status Enum
 *
 * Represents the operational status of a tenant.
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Check if tenant can perform operations.
     */
    public function canOperate(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if tenant can upload documents.
     */
    public function canUploadDocuments(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if tenant can process documents.
     */
    public function canProcessDocuments(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Suspended => 'yellow',
            self::Cancelled => 'red',
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
