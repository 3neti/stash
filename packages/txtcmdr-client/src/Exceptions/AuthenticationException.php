<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Exceptions;

/**
 * Thrown when API authentication fails (invalid/expired token)
 */
class AuthenticationException extends TxtcmdrException
{
    /**
     * Create exception for invalid token
     */
    public static function invalidToken(): self
    {
        return new self('Invalid txtcmdr API token. Please check your campaign configuration.');
    }

    /**
     * Create exception for expired token
     */
    public static function expiredToken(): self
    {
        return new self('txtcmdr API token has expired. Please regenerate token.');
    }

    /**
     * Create exception for missing token
     */
    public static function missingToken(): self
    {
        return new self('txtcmdr API token is required but not configured.');
    }
}
