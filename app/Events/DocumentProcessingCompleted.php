<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when document processing completes successfully.
 */
class DocumentProcessingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Campaign $campaign,
        public Document $document,
        public DocumentJob $documentJob
    ) {
    }

    /**
     * Get event payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'document.processing.completed',
            'timestamp' => now()->toISOString(),
            'data' => [
                'document' => [
                    'id' => $this->document->id,
                    'uuid' => $this->document->uuid,
                    'original_filename' => $this->document->original_filename,
                    'mime_type' => $this->document->mime_type,
                    'size_bytes' => $this->document->size_bytes,
                    'status' => $this->document->status,
                ],
                'job' => [
                    'id' => $this->documentJob->id,
                    'status' => $this->documentJob->status,
                    'started_at' => $this->documentJob->started_at?->toISOString(),
                    'completed_at' => $this->documentJob->completed_at?->toISOString(),
                    'pipeline' => $this->documentJob->pipeline,
                ],
                'campaign' => [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                ],
            ],
        ];
    }
}
