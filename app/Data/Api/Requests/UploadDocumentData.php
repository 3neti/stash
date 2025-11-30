<?php

declare(strict_types=1);

namespace App\Data\Api\Requests;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Mimes;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Upload Document Request DTO
 *
 * Validates and structures document upload requests.
 */
class UploadDocumentData extends Data
{
    public function __construct(
        #[Required]
        #[File]
        #[Mimes(['pdf', 'png', 'jpg', 'jpeg', 'tiff'])]
        #[Max(10240)] // 10MB in kilobytes
        public UploadedFile $file,

        public ?array $metadata = null,
    ) {}

    /**
     * Additional validation rules not covered by attributes.
     */
    public static function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
            'metadata' => 'nullable|array',
            'metadata.description' => 'nullable|string|max:500',
            'metadata.reference_id' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get file size in bytes.
     */
    public function getFileSizeBytes(): int
    {
        return $this->file->getSize();
    }

    /**
     * Get original filename.
     */
    public function getOriginalFilename(): string
    {
        return $this->file->getClientOriginalName();
    }

    /**
     * Get MIME type.
     */
    public function getMimeType(): string
    {
        return $this->file->getMimeType();
    }

    /**
     * Calculate SHA-256 hash of file.
     */
    public function getFileHash(): string
    {
        return hash_file('sha256', $this->file->getRealPath());
    }
}
