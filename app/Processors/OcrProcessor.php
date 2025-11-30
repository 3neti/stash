<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Extracts text from images and PDFs using Tesseract OCR.
 */
class OcrProcessor extends AbstractProcessor
{
    protected string $name = 'Tesseract OCR';

    protected string $category = 'ocr';

    /**
     * Check if document can be processed (images and PDFs only).
     */
    public function canProcess(Document $document): bool
    {
        $supportedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/tiff',
            'image/bmp',
            'image/webp',
            'application/pdf',
        ];

        return in_array($document->mime_type, $supportedMimeTypes, true);
    }

    /**
     * Process the document and extract text.
     *
     * @throws \Exception
     */
    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Download document to temporary file
        $tempPath = $this->downloadDocument($document);

        try {
            // Extract text using Tesseract
            $result = $this->extractText($tempPath, $config->config);

            return $result;
        } finally {
            // Always clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Download document from storage to temporary file.
     *
     * @throws \Exception
     */
    private function downloadDocument(Document $document): string
    {
        if (! $document->fileExists()) {
            throw new \Exception("Document file not found: {$document->storage_path}");
        }

        // Create temp file
        $tempPath = sys_get_temp_dir().'/'.uniqid('ocr_', true).'_'.$document->original_filename;

        // Download from storage
        $contents = Storage::disk($document->storage_disk)->get($document->storage_path);
        file_put_contents($tempPath, $contents);

        return $tempPath;
    }

    /**
     * Extract text using Tesseract OCR.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function extractText(string $filePath, array $config): array
    {
        $ocr = new TesseractOCR($filePath);

        // Configure language (default: English)
        $language = $config['language'] ?? 'eng';
        $ocr->lang($language);

        // Configure page segmentation mode (PSM)
        // 3 = Fully automatic page segmentation, but no OSD (default)
        // 6 = Assume a single uniform block of text
        $psm = $config['psm'] ?? 3;
        $ocr->psm($psm);

        // Set OCR Engine Mode (OEM)
        // 3 = Default, based on what is available (LSTM, Legacy, or both)
        $oem = $config['oem'] ?? 3;
        $ocr->oem($oem);

        // Execute OCR
        $text = $ocr->run();

        // Note: Confidence detection requires hocr output which needs a separate Tesseract call
        // Skipping for performance - can be added later if needed
        $confidence = null;

        return [
            'text' => $text,
            'language' => $language,
            'confidence' => $confidence,
            'char_count' => mb_strlen($text),
            'word_count' => str_word_count($text),
            'psm' => $psm,
            'oem' => $oem,
        ];
    }

    /**
     * Parse average confidence from HOCR output.
     */
    private function parseConfidenceFromHocr(string $hocr): ?float
    {
        // Extract all x_wconf (word confidence) values from HOCR
        preg_match_all('/x_wconf (\d+)/', $hocr, $matches);

        if (empty($matches[1])) {
            return null;
        }

        $confidences = array_map('floatval', $matches[1]);
        $average = array_sum($confidences) / count($confidences);

        return round($average / 100, 4); // Convert 0-100 to 0-1
    }
}
