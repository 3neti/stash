<?php

declare(strict_types=1);

namespace Tests\Feature\DeadDrop\Mocks;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use App\Processors\AbstractProcessor;

/**
 * Mock OCR Processor for testing.
 * Returns synthetic OCR output without calling real Tesseract.
 */
class MockOcrProcessor extends AbstractProcessor
{
    protected string $name = 'Mock OCR';

    protected string $category = 'ocr';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        return [
            'text' => 'Hello World This is test invoice with number 12345 dated 2024-11-30 from ACME Corp for $1,234.56',
            'language' => 'eng',
            'confidence' => 0.95,
            'char_count' => 85,
            'word_count' => 15,
            'psm' => 3,
            'oem' => 3,
        ];
    }
}
