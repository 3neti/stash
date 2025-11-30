<?php

declare(strict_types=1);

namespace Tests\Feature\DeadDrop\Mocks;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use App\Processors\AbstractProcessor;

/**
 * Mock Classification Processor for testing.
 * Returns synthetic classification output without calling real OpenAI.
 */
class MockClassificationProcessor extends AbstractProcessor
{
    protected string $name = 'Mock Classification';

    protected string $category = 'classification';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        return [
            'category' => 'invoice',
            'confidence' => 0.92,
            'reasoning' => 'Document contains invoice number, amounts, and vendor information typical of invoices',
            'tokens_used' => 145,
            'model' => 'gpt-4o-mini',
            'processor' => $this->name,
            'version' => '1.0.0',
            'categories_available' => $config->config['categories'] ?? ['invoice', 'receipt', 'contract', 'other'],
        ];
    }
}
