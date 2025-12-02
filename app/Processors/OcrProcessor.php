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
        $language = $config['language'] ?? 'eng';
        $psm = $config['psm'] ?? 3; // 3 = automatic page segmentation
        $oem = $config['oem'] ?? 3; // 3 = default engine mode
        $dpi = $config['dpi'] ?? 300;

        $imagePaths = [];
        $cleanup = function () use (&$imagePaths): void {
            foreach ($imagePaths as $img) {
                if (is_string($img) && file_exists($img)) {
                    @unlink($img);
                }
            }
        };

        try {
            // If input is a PDF, rasterize to images first (Imagick -> pdftoppm fallback)
            if (str_ends_with(strtolower($filePath), '.pdf')) {
                $imagePaths = $this->convertPdfToImages($filePath, $dpi);
            } else {
                $imagePaths = [$filePath];
            }

            $texts = [];
            foreach ($imagePaths as $index => $imgPath) {
                $ocr = new TesseractOCR($imgPath);
                $ocr->lang($language)->psm($psm)->oem($oem);
                $texts[] = $ocr->run();
            }

            $text = trim(implode("\n\n", $texts));
            $confidence = null; // optional, requires hOCR pass

            return [
                'text' => $text,
                'language' => $language,
                'confidence' => $confidence,
                'char_count' => mb_strlen($text),
                'word_count' => str_word_count($text),
                'psm' => $psm,
                'oem' => $oem,
                'pages' => count($imagePaths),
            ];
        } finally {
            $cleanup();
        }
    }

    /**
     * Convert a PDF file to a set of PNG images (one per page).
     * Prefers Imagick; falls back to `pdftoppm` if available.
     *
     * @return array<int,string> Absolute paths to generated PNGs
     */
    private function convertPdfToImages(string $pdfPath, int $dpi = 300): array
    {
        $generated = [];

        // Prefer Imagick if available
        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->setResolution($dpi, $dpi);
                // Read all pages
                $imagick->readImage($pdfPath);
                $imagick->setImageFormat('png');

                foreach ($imagick as $page) {
                    $tmp = tempnam(sys_get_temp_dir(), 'ocr_');
                    $pngPath = $tmp . '.png';
                    $page->writeImage($pngPath);
                    $generated[] = $pngPath;
                }

                $imagick->clear();
                $imagick->destroy();

                if (! empty($generated)) {
                    return $generated;
                }
            } catch (\Throwable $e) {
                // Fall through to pdftoppm
            }
        }

        // Fallback to pdftoppm if available
        $outBase = sys_get_temp_dir() . '/ocr_' . uniqid();
        $cmd = sprintf(
            'pdftoppm -png -r %d %s %s 2>&1',
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($outBase)
        );
        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);

        if ($code === 0) {
            $files = glob($outBase . '-*.png') ?: [];
            if (! empty($files)) {
                // Ensure absolute paths
                foreach ($files as $f) {
                    $generated[] = realpath($f) ?: $f;
                }
                return $generated;
            }
        }

        // If we reach here, no conversion path succeeded
        throw new \RuntimeException('PDF to image conversion failed. Ensure Imagick (PHP extension) or pdftoppm is installed.');
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

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'confidence' => ['type' => ['number', 'null']],
                'pages' => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
            'required' => ['text'],
        ];
    }
}
