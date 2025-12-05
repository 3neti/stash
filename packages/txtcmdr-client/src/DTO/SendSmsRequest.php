<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\DTO;

use LBHurtado\DeadDrop\TxtcmdrClient\Exceptions\InvalidRecipientException;

/**
 * Data Transfer Object for SMS send requests
 */
class SendSmsRequest
{
    /**
     * @param  array<string>  $recipients  Phone numbers (E.164 or local format)
     * @param  string  $message  Message content
     * @param  string|null  $senderId  Sender ID (optional)
     */
    public function __construct(
        public readonly array $recipients,
        public readonly string $message,
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
        ];

        if ($this->senderId !== null) {
            $data['sender_id'] = $this->senderId;
        }

        return $data;
    }
}
