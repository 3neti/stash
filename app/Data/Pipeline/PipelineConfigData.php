<?php

declare(strict_types=1);

namespace App\Data\Pipeline;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Pipeline Configuration Data Transfer Object
 *
 * Represents the entire pipeline configuration from a Campaign.
 */
class PipelineConfigData extends Data
{
    public function __construct(
        #[DataCollectionOf(ProcessorConfigData::class)]
        public readonly DataCollection $processors,
    ) {}
}
