<?php

declare(strict_types=1);

namespace App\Data\Api\Resources;

use App\Enums\DocumentMimeType;
use App\Models\Document;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\EnumTransformer;

/**
 * Document API Resource DTO
 * 
 * Represents a document in API responses with proper enum handling.
 */
class DocumentData extends Data
{
    public function __construct(
        public string $id,
        public string $uuid,
        public string $campaign_id,
        public string $original_filename,
        
        #[WithCast(EnumCast::class, DocumentMimeType::class)]
        #[WithTransformer(EnumTransformer::class)]
        public DocumentMimeType $mime_type,
        
        public int $size_bytes,
        public string $status,
        public ?string $storage_path = null,
        public ?DocumentJobData $job = null,
        public ?array $metadata = null,
        public ?string $created_at = null,
        public ?string $processed_at = null,
    ) {}
    
    /**
     * Create from Document model.
     */
    public static function fromModel(Document $document): self
    {
        return new self(
            id: $document->id,
            uuid: $document->uuid,
            campaign_id: $document->campaign_id,
            original_filename: $document->original_filename,
            mime_type: DocumentMimeType::from($document->mime_type),
            size_bytes: $document->size_bytes,
            status: $document->state::$name ?? 'pending',
            storage_path: $document->storage_path,
            job: $document->relationLoaded('documentJob') && $document->documentJob 
                ? DocumentJobData::fromModel($document->documentJob) 
                : null,
            metadata: $document->metadata,
            created_at: $document->created_at?->toIso8601String(),
            processed_at: $document->processed_at?->toIso8601String(),
        );
    }
}
