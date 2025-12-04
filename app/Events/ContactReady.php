<?php

declare(strict_types=1);

namespace App\Events;

use App\Data\ContactData;
use App\Models\Contact;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ContactReady Event
 * 
 * Broadcasts when a Contact is ready after KYC verification completes.
 * Uses public channel with transaction ID for security without authentication.
 */
class ContactReady implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Ensure event is dispatched after database transaction commits.
     */
    public bool $afterCommit = true;

    public function __construct(
        public readonly Contact $contact,
        public readonly string $transactionId,
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
            'contact' => ContactData::fromContact($this->contact),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'contact.ready';
    }
}
