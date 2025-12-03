<?php

declare(strict_types=1);

namespace App\Services\Processors\PortPHP;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Document;
use Port\Csv\CsvReader;
use Port\Steps\StepAggregator;
use Port\Steps\Step\ValueConverterStep;
use Port\ValueConverter\DateTimeValueConverter;
use Port\Writer\ArrayWriter;

/**
 * CSV Import Processor
 *
 * Imports CSV files using PortPHP's CSV reader and workflow system.
 *
 * Configuration options:
 * - delimiter: Column delimiter (default: ',')
 * - enclosure: Field enclosure character (default: '"')
 * - has_headers: First row contains headers (default: true)
 * - header_row: Row number containing headers (default: 0)
 * - date_columns: Array of column names to convert to DateTime
 * - date_format: Format for date conversion (default: 'Y-m-d')
 *
 * Example config:
 * {
 *   "delimiter": ",",
 *   "has_headers": true,
 *   "date_columns": ["hire_date", "birth_date"],
 *   "date_format": "Y-m-d"
 * }
 */
class CsvImportProcessor extends BasePortProcessor
{
    /**
     * Configure the PortPHP workflow for CSV import.
     */
    protected function configureWorkflow(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): StepAggregator {
        // 1. Get CSV file path
        $csvPath = $this->getDocumentPath($document);

        // 2. Create CSV reader
        $file = new \SplFileObject($csvPath);
        $reader = new CsvReader($file);

        // 3. Configure CSV reader from config
        $delimiter = $config->get('delimiter', ',');
        $enclosure = $config->get('enclosure', '"');
        $escape = $config->get('escape', '\\');

        $reader->setDelimiter($delimiter);
        $reader->setEnclosure($enclosure);
        $reader->setEscape($escape);

        // 4. Set header row if configured
        $hasHeaders = $config->get('has_headers', true);
        if ($hasHeaders) {
            $headerRow = $config->get('header_row', 0);
            $reader->setHeaderRowNumber($headerRow);
        }

        // 5. Create workflow from reader
        $workflow = new StepAggregator($reader);

        // 6. Add date converters if specified
        $dateColumns = $config->get('date_columns', []);
        if (! empty($dateColumns)) {
            $dateFormat = $config->get('date_format', 'Y-m-d');
            $dateConverter = new DateTimeValueConverter($dateFormat);

            $converterStep = new ValueConverterStep();
            foreach ($dateColumns as $column) {
                $converterStep->add([$column => $dateConverter]);
            }

            $workflow->addStep($converterStep);
        }

        return $workflow;
    }

    /**
     * Extract output data from PortPHP result.
     */
    protected function extractOutputData(\Port\Result $portResult, ArrayWriter $arrayWriter): array
    {
        return [
            'rows_imported' => $portResult->getSuccessCount(),
            'rows_failed' => $portResult->getErrorCount(),
            'total_rows' => $portResult->getTotalProcessedCount(),
            'data' => $arrayWriter->getData(),
            'has_errors' => $portResult->hasErrors(),
        ];
    }

    /**
     * Generate artifacts (optional JSON export).
     */
    protected function generateArtifacts(
        array $outputData,
        Document $document,
        ProcessorConfigData $config
    ): array {
        // Optionally export imported data as JSON artifact
        if ($config->get('export_json', false) && ! empty($outputData['data'])) {
            return [
                'extracted-data' => $this->exportToJson($outputData['data']),
            ];
        }

        return [];
    }

    /**
     * Get the processor name.
     */
    public function getName(): string
    {
        return 'CSV Importer';
    }

    /**
     * Check if document is a CSV file.
     */
    public function canProcess(Document $document): bool
    {
        $mimeType = $document->mime_type;

        return in_array($mimeType, [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel', // Excel CSV export
        ]) && parent::canProcess($document);
    }
}
