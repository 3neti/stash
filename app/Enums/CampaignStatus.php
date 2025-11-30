<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Campaign Status Enum
 *
 * Represents the operational status of a campaign.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        };
    }

    /**
     * Check if campaign can accept documents.
     */
    public function canAcceptDocuments(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if campaign can process documents.
     */
    public function canProcessDocuments(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if campaign is editable.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Paused], true);
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'green',
            self::Paused => 'yellow',
            self::Archived => 'red',
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
