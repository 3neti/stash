<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Processor Exception
 *
 * Thrown when a processor encounters an error during execution.
 */
class ProcessorException extends Exception
{
    public static function processingFailed(string $processorName, string $reason): self
    {
        return new self("Processor '{$processorName}' failed: {$reason}");
    }

    public static function invalidConfiguration(string $processorName, string $reason): self
    {
        return new self("Invalid configuration for processor '{$processorName}': {$reason}");
    }

    public static function documentNotSupported(string $processorName, string $mimeType): self
    {
        return new self("Processor '{$processorName}' does not support documents of type '{$mimeType}'");
    }
}
