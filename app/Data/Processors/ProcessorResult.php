<?php

declare(strict_types=1);

namespace App\Data\Processors;

/**
 * Result of a processor execution.
 *
 * Encapsulates success/failure, output data, errors, and metadata.
 * Enables testable return types from processors.
 */
class ProcessorResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $output = [],
        public readonly ?string $error = null,
        public readonly array $metadata = [],
        public readonly ?int $tokensUsed = null,
        public readonly ?int $costCredits = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(array $output, array $metadata = []): self
    {
        return new self(
            success: true,
            output: $output,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * Check if result indicates success.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if result indicates failure.
     */
    public function isFailed(): bool
    {
        return ! $this->success;
    }
}
