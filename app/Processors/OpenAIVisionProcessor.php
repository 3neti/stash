<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;

class OpenAIVisionProcessor extends AbstractProcessor
{
    protected string $name = 'OpenAI Vision OCR';
    protected string $category = 'ocr';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Stub implementation for testing
        return [
            'text' => 'OpenAI Vision OCR stub result',
            'confidence' => 0.85,
            'model_used' => $config->config['model'] ?? 'gpt-4-vision-preview',
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
                'model_used' => ['type' => 'string'],
            ],
            'required' => ['text'],
        ];
    }
}
