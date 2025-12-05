<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient\Exceptions;

/**
 * Thrown when client configuration is invalid
 */
class ConfigurationException extends TxtcmdrException
{
    /**
     * Create exception for missing configuration
     */
    public static function missingConfig(string $key): self
    {
        return new self("Required configuration key is missing: {$key}");
    }

    /**
     * Create exception for invalid URL
     */
    public static function invalidUrl(string $url): self
    {
        return new self("Invalid txtcmdr API URL: {$url}");
    }

    /**
     * Create exception for disabled txtcmdr
     */
    public static function disabled(): self
    {
        return new self('txtcmdr SMS is not enabled for this campaign.');
    }
}
