<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Document MIME Type Enum
 *
 * Represents allowed document file types for upload.
 */
enum DocumentMimeType: string
{
    case Pdf = 'application/pdf';
    case Png = 'image/png';
    case Jpeg = 'image/jpeg';
    case Jpg = 'image/jpg';
    case Tiff = 'image/tiff';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF Document',
            self::Png => 'PNG Image',
            self::Jpeg, self::Jpg => 'JPEG Image',
            self::Tiff => 'TIFF Image',
        };
    }

    /**
     * Get file extension.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Png => 'png',
            self::Jpeg, self::Jpg => 'jpg',
            self::Tiff => 'tiff',
        };
    }

    /**
     * Check if mime type is an image.
     */
    public function isImage(): bool
    {
        return in_array($this, [
            self::Png,
            self::Jpeg,
            self::Jpg,
            self::Tiff,
        ], true);
    }

    /**
     * Check if mime type is a PDF.
     */
    public function isPdf(): bool
    {
        return $this === self::Pdf;
    }

    /**
     * Get all enum values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get validation rule for Laravel.
     */
    public static function validationRule(): string
    {
        return 'mimes:pdf,png,jpg,jpeg,tiff';
    }

    /**
     * Try to create from file extension.
     */
    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'pdf' => self::Pdf,
            'png' => self::Png,
            'jpg', 'jpeg' => self::Jpeg,
            'tiff', 'tif' => self::Tiff,
            default => null,
        };
    }
}
