<?php

declare(strict_types=1);

namespace App\Data\Pipeline;

use Spatie\LaravelData\Data;

/**
 * Processor Configuration Data Transfer Object
 * 
 * Represents a single processor configuration from the pipeline config.
 */
class ProcessorConfigData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $config,
        public readonly ?array $next = null,
        public readonly ?array $branches = null,
    ) {}
}
