<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;

class DataEnricherProcessor extends AbstractProcessor
{
    protected string $name = 'Data Enricher';
    protected string $category = 'enrichment';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Stub implementation for testing
        return [
            'enriched_data' => [],
            'sources_used' => $config->config['enrichment_sources'] ?? [],
            'enrichment_time_ms' => 125,
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enriched_data' => ['type' => 'object'],
                'sources_used' => ['type' => 'array', 'items' => ['type' => 'string']],
                'enrichment_time_ms' => ['type' => 'number'],
            ],
            'required' => ['enriched_data'],
        ];
    }
}
