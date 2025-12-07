<?php

declare(strict_types=1);

namespace App\Data\Campaigns;

use App\Data\Pipeline\ProcessorConfigData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Campaign Import Data Transfer Object
 *
 * Used for importing campaigns from JSON/YAML files with automatic validation.
 */
class CampaignImportData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $name,
        public readonly ?string $slug,
        public readonly ?string $description,
        #[Required, In(['template', 'custom', 'meta'])]
        public readonly string $type,
        #[In(['draft', 'active', 'paused', 'archived'])]
        public readonly string $state = 'draft',
        #[Required, DataCollectionOf(ProcessorConfigData::class), Min(1)]
        public readonly DataCollection $processors,
        public readonly ?array $settings,
        public readonly ?array $allowed_mime_types,
        public readonly ?int $max_file_size_bytes,
        public readonly ?int $max_concurrent_jobs,
        public readonly ?int $retention_days,
        public readonly ?array $checklist_template,
    ) {}
}
