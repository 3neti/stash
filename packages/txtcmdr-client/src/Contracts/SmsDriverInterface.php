<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Contracts;

/**
 * Contract for SMS drivers (txtcmdr, Twilio, etc.)
 *
 * All SMS providers must implement this interface
 */
interface SmsDriverInterface
{
    /**
     * Send SMS to one or more recipients
     *
     * @param  array<string>  $recipients  Phone numbers
     * @param  string  $message  Message content
     * @param  string|null  $senderId  Sender ID (optional)
     * @return mixed Response from SMS provider
     */
    public function send(array $recipients, string $message, ?string $senderId = null): mixed;
}
