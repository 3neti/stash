<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use Illuminate\Support\Str;

class EmailNotifierProcessor extends AbstractProcessor
{
    protected string $name = 'Email Notifier';
    protected string $category = 'notification';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Stub implementation for testing
        return [
            'sent' => true,
            'recipients' => $config->config['recipients'] ?? [],
            'message_id' => (string) Str::uuid(),
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sent' => ['type' => 'boolean'],
                'recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'message_id' => ['type' => 'string'],
            ],
            'required' => ['sent'],
        ];
    }
}
