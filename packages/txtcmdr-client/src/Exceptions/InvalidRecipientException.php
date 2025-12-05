<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Exceptions;

/**
 * Thrown when recipient data is invalid
 */
class InvalidRecipientException extends TxtcmdrException
{
    /**
     * Create exception for empty recipients
     */
    public static function empty(): self
    {
        return new self('Recipients array cannot be empty.');
    }

    /**
     * Create exception for invalid phone format
     */
    public static function invalidFormat(string $phone): self
    {
        return new self("Invalid phone number format: {$phone}");
    }

    /**
     * Create exception for missing mobile number
     */
    public static function missingMobile(): self
    {
        return new self('No mobile number found for recipient.');
    }
}
