<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;

class SchemaValidatorProcessor extends AbstractProcessor
{
    protected string $name = 'Schema Validator';
    protected string $category = 'validation';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Stub implementation for testing
        return [
            'valid' => true,
            'errors' => [],
            'validated_data' => [],
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'valid' => ['type' => 'boolean'],
                'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                'validated_data' => ['type' => 'object'],
            ],
            'required' => ['valid'],
        ];
    }
}
