<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;

class S3StorageProcessor extends AbstractProcessor
{
    protected string $name = 'S3 Storage';
    protected string $category = 'storage';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Stub implementation for testing
        return [
            'stored' => true,
            's3_path' => 's3://bucket/processed/documents/' . $document->uuid,
            'storage_time_ms' => 250,
            'file_size_bytes' => 2048,
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'stored' => ['type' => 'boolean'],
                's3_path' => ['type' => 'string'],
                'storage_time_ms' => ['type' => 'number'],
                'file_size_bytes' => ['type' => 'integer'],
            ],
            'required' => ['stored', 's3_path'],
        ];
    }
}
