<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DocumentSignedEvent
 * 
 * Broadcasts when a document has been digitally signed after workflow completion.
 * Uses public channel with transaction ID (same pattern as ContactReady).
 */
class DocumentSignedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Ensure event is dispatched after database transaction commits.
     */
    public bool $afterCommit = true;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $documentJobId,
        public readonly array $signedDocument,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("kyc.{$this->transactionId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document_job_id' => $this->documentJobId,
            'transaction_id' => $this->transactionId,
            'signed_document' => $this->signedDocument,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.signed';
    }
}
