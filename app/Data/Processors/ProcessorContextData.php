<?php

declare(strict_types=1);

namespace App\Data\Processors;

use Spatie\LaravelData\Data;

/**
 * Processor Context Data Transfer Object
 * 
 * Contains runtime context for processor execution.
 */
class ProcessorContextData extends Data
{
    public function __construct(
        public readonly string $documentJobId,
        public readonly int $processorIndex,
        public readonly array $previousOutputs = [],
        public readonly array $metadata = [],
    ) {}
}
