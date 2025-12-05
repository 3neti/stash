<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\DTO;

/**
 * Data Transfer Object for SMS send responses
 */
class SendSmsResponse
{
    /**
     * @param  bool  $success  Whether the request was successful
     * @param  int|null  $jobsDispatched  Number of SMS jobs queued (for immediate sends)
     * @param  int|null  $scheduledMessageId  ID of scheduled message (for scheduled sends)
     * @param  string|null  $message  Response message from API
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?int $jobsDispatched = null,
        public readonly ?int $scheduledMessageId = null,
        public readonly ?string $message = null,
    ) {
    }

    /**
     * Create from API response array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // txtcmdr returns: {"status": "queued", "count": 1, "recipients": [...]}
        // or for scheduled: {"status": "scheduled", "scheduled_message_id": 123}
        $isSuccess = in_array($data['status'] ?? '', ['queued', 'scheduled'], true);

        return new self(
            success: $isSuccess,
            jobsDispatched: $data['count'] ?? $data['jobs_dispatched'] ?? null,
            scheduledMessageId: $data['scheduled_message_id'] ?? null,
            message: $data['message'] ?? $data['status'] ?? null,
        );
    }

    /**
     * Check if this is an immediate send response
     */
    public function isImmediateSend(): bool
    {
        return $this->jobsDispatched !== null;
    }

    /**
     * Check if this is a scheduled send response
     */
    public function isScheduledSend(): bool
    {
        return $this->scheduledMessageId !== null;
    }
}
