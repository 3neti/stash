<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Exceptions;

use Throwable;

/**
 * Thrown when API request fails (network, timeout, server errors)
 */
class ApiRequestException extends TxtcmdrException
{
    /**
     * Create exception for network errors
     */
    public static function networkError(string $message, ?Throwable $previous = null): self
    {
        return new self("Network error: {$message}", 0, $previous);
    }

    /**
     * Create exception for timeout
     */
    public static function timeout(): self
    {
        return new self('Request to txtcmdr API timed out.');
    }

    /**
     * Create exception for server errors
     */
    public static function serverError(int $statusCode, string $body = ''): self
    {
        return new self("txtcmdr API server error (HTTP {$statusCode}): {$body}");
    }

    /**
     * Create exception for unexpected response format
     */
    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid API response format: {$reason}");
    }
}
