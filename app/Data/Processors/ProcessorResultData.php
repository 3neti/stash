<?php

declare(strict_types=1);

namespace App\Data\Processors;

use Spatie\LaravelData\Data;

/**
 * Processor Result Data Transfer Object
 * 
 * Represents the result of a processor execution.
 */
class ProcessorResultData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly array $output,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
        public readonly ?int $tokensUsed = null,
        public readonly ?int $costCredits = null,
    ) {}
}
