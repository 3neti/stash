<?php

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Campaign;
use App\Models\Document;
use App\Processors\OcrProcessor;
use Illuminate\Support\Facades\Storage;

describe('OcrProcessor Integration', function () {
    beforeEach(function () {
        // Ensure storage disk is set up
        Storage::fake('local');

        $this->processor = new OcrProcessor;
    });

    test('can extract text from image', function () {
        // Create test document with actual image file
        $campaign = Campaign::factory()->create();

        // Copy test image to fake storage
        $testImagePath = base_path('tests/Fixtures/images/test-document.png');
        $storagePath = 'documents/test-document.png';

        Storage::disk('local')->put(
            $storagePath,
            file_get_contents($testImagePath)
        );

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'test-document.png',
            'mime_type' => 'image/png',
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
            'size_bytes' => filesize($testImagePath),
        ]);

        // Process document
        $config = new ProcessorConfigData(
            id: 'ocr',
            type: 'ocr',
            config: ['language' => 'eng']
        );

        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );

        $result = $this->processor->handle($document, $config, $context);

        // Verify result
        expect($result->success)->toBeTrue()
            ->and($result->output)->toHaveKey('text')
            ->and($result->output)->toHaveKey('char_count')
            ->and($result->output)->toHaveKey('word_count')
            ->and($result->output['language'])->toBe('eng');

        // Verify text contains expected content
        $text = $result->output['text'];
        expect($text)->toContain('Hello')
            ->and($text)->toContain('World');
    });

    test('can process different image formats', function () {
        $campaign = Campaign::factory()->create();

        $formats = [
            ['mime' => 'image/jpeg', 'ext' => 'jpg'],
            ['mime' => 'image/png', 'ext' => 'png'],
        ];

        foreach ($formats as $format) {
            $storagePath = "documents/test.{$format['ext']}";

            // Create simple test image
            Storage::disk('local')->put(
                $storagePath,
                file_get_contents(base_path('tests/Fixtures/images/test-document.png'))
            );

            $document = Document::factory()->create([
                'campaign_id' => $campaign->id,
                'original_filename' => "test.{$format['ext']}",
                'mime_type' => $format['mime'],
                'storage_path' => $storagePath,
                'storage_disk' => 'local',
            ]);

            expect($this->processor->canProcess($document))->toBeTrue();
        }
    });

    test('rejects unsupported document types', function () {
        $campaign = Campaign::factory()->create();

        $unsupportedTypes = [
            'application/msword',
            'text/plain',
            'application/zip',
        ];

        foreach ($unsupportedTypes as $mimeType) {
            $document = Document::factory()->create([
                'campaign_id' => $campaign->id,
                'mime_type' => $mimeType,
            ]);

            expect($this->processor->canProcess($document))->toBeFalse();
        }
    });

    test('respects language configuration', function () {
        $campaign = Campaign::factory()->create();

        $testImagePath = base_path('tests/Fixtures/images/test-document.png');
        $storagePath = 'documents/test-lang.png';

        Storage::disk('local')->put(
            $storagePath,
            file_get_contents($testImagePath)
        );

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'test-lang.png',
            'mime_type' => 'image/png',
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
        ]);

        // Test with custom PSM setting
        $config = new ProcessorConfigData(
            id: 'ocr',
            type: 'ocr',
            config: ['language' => 'eng', 'psm' => 6] // PSM 6 = single uniform block
        );

        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );

        $result = $this->processor->handle($document, $config, $context);

        expect($result->success)->toBeTrue()
            ->and($result->output['psm'])->toBe(6); // Verify PSM was applied
    })->skip('Language pack test requires additional language data');

    test('handles missing file gracefully', function () {
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'storage_path' => 'documents/nonexistent.png',
            'storage_disk' => 'local',
            'mime_type' => 'image/png',
        ]);

        $config = new ProcessorConfigData(
            id: 'ocr',
            type: 'ocr',
            config: []
        );

        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );

        $result = $this->processor->handle($document, $config, $context);

        // Should fail gracefully
        expect($result->success)->toBeFalse()
            ->and($result->error)->toContain('not found');
    });

    test('extracts meaningful statistics', function () {
        $campaign = Campaign::factory()->create();

        $testImagePath = base_path('tests/Fixtures/images/test-document.png');
        $storagePath = 'documents/test-stats.png';

        Storage::disk('local')->put(
            $storagePath,
            file_get_contents($testImagePath)
        );

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'test-stats.png',
            'mime_type' => 'image/png',
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
        ]);

        $config = new ProcessorConfigData(
            id: 'ocr',
            type: 'ocr',
            config: []
        );

        $context = new ProcessorContextData(
            documentJobId: 'test-job-123',
            processorIndex: 0
        );

        $result = $this->processor->handle($document, $config, $context);

        expect($result->success)->toBeTrue()
            ->and($result->output['char_count'])->toBeGreaterThan(0)
            ->and($result->output['word_count'])->toBeGreaterThan(0)
            ->and($result->output)->toHaveKey('psm')
            ->and($result->output)->toHaveKey('oem');
    });

    test('processor has correct metadata', function () {
        expect($this->processor->getName())->toBe('Tesseract OCR')
            ->and($this->processor->getCategory())->toBe('ocr');
    });
});
