<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\DTO;

use Carbon\Carbon;
use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\InvalidRecipientException;

/**
 * Data Transfer Object for scheduled SMS requests
 */
class ScheduleSmsRequest
{
    /**
     * @param  array<string>  $recipients  Phone numbers (E.164 or local format)
     * @param  string  $message  Message content
     * @param  Carbon  $scheduledAt  When to send the message
     * @param  string|null  $senderId  Sender ID (optional)
     */
    public function __construct(
        public readonly array $recipients,
        public readonly string $message,
        public readonly Carbon $scheduledAt,
        public readonly ?string $senderId = null,
    ) {
        $this->validate();
    }

    /**
     * Validate request data
     */
    protected function validate(): void
    {
        if (empty($this->recipients)) {
            throw InvalidRecipientException::empty();
        }

        if (empty($this->message)) {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }

        if ($this->scheduledAt->isPast()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future.');
        }
    }

    /**
     * Convert to array for API request
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'recipients' => $this->recipients,
            'message' => $this->message,
            'scheduled_at' => $this->scheduledAt->toIso8601String(),
        ];

        if ($this->senderId !== null) {
            $data['sender_id'] = $this->senderId;
        }

        return $data;
    }
}
