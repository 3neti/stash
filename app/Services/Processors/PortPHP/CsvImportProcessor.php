<?php

declare(strict_types=1);

namespace App\Services\Processors\PortPHP;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Document;
use Port\Csv\CsvReader;
use Port\Steps\StepAggregator;
use Port\Steps\Step\ValueConverterStep;
use Port\Steps\Step\MappingStep;
use Port\Steps\Step\FilterStep;
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
 * - escape: Escape character (default: '\\')
 * - has_headers: First row contains headers (default: true)
 * - header_row: Row number containing headers (default: 0)
 * - date_columns: Array of column names to convert to DateTime
 * - date_format: Format for date conversion (default: 'Y-m-d')
 * - transformations: Array of field transformations to apply
 * - filters: Array of filter conditions to skip rows
 * - export_json: Export imported data as JSON artifact (default: false)
 *
 * Transformation options:
 * - uppercase: Array of columns to uppercase
 * - lowercase: Array of columns to lowercase
 * - trim: Array of columns to trim whitespace
 * - integer: Array of columns to convert to integers
 * - float: Array of columns to convert to floats
 * - boolean: Array of columns to convert to booleans
 * - mapping: Custom callback mapping function
 *
 * Filter options:
 * - required_columns: Array of columns that must not be empty
 * - min_values: Object with column => minimum value constraints
 * - max_values: Object with column => maximum value constraints
 * - allowed_values: Object with column => array of allowed values
 * - custom_filter: Custom callback filter function
 *
 * Example config:
 * {
 *   "delimiter": ",",
 *   "has_headers": true,
 *   "date_columns": ["hire_date"],
 *   "transformations": {
 *     "uppercase": ["department"],
 *     "trim": ["email"],
 *     "integer": ["salary"]
 *   },
 *   "filters": {
 *     "required_columns": ["email"],
 *     "min_values": {"salary": 0},
 *     "allowed_values": {"department": ["Engineering", "Sales", "Marketing", "HR"]}
 *   },
 *   "export_json": true
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

        // 2. Get CSV reader configuration
        $delimiter = $config->config['delimiter'] ?? ',';
        $enclosure = $config->config['enclosure'] ?? '"';
        $escape = $config->config['escape'] ?? '\\';

        // 3. Create CSV reader with configuration
        $file = new \SplFileObject($csvPath);
        $reader = new CsvReader($file, $delimiter, $enclosure, $escape);

        // 4. Set header row if configured
        $hasHeaders = $config->config['has_headers'] ?? true;
        if ($hasHeaders) {
            $headerRow = $config->config['header_row'] ?? 0;
            $reader->setHeaderRowNumber($headerRow);
        }

        // 5. Create workflow from reader
        $workflow = new StepAggregator($reader);

        // 6. Add date converters if specified
        $dateColumns = $config->config['date_columns'] ?? [];
        if (! empty($dateColumns)) {
            $dateFormat = $config->config['date_format'] ?? 'Y-m-d';
            $dateConverter = new DateTimeValueConverter($dateFormat);

            $converterStep = new ValueConverterStep();
            foreach ($dateColumns as $column) {
                $converterStep->add([$column => $dateConverter]);
            }

            $workflow->addStep($converterStep);
        }

        // 7. Add filter step if specified
        $filters = $config->config['filters'] ?? [];
        if (! empty($filters)) {
            $filterStep = new FilterStep(function ($row) use ($filters) {
                return $this->applyFilters($row, $filters);
            });
            $workflow->addStep($filterStep);
        }

        // 8. Add transformation step if specified
        $transformations = $config->config['transformations'] ?? [];
        if (! empty($transformations)) {
            $mappingStep = new MappingStep(function ($row) use ($transformations) {
                return $this->applyTransformations($row, $transformations);
            });
            $workflow->addStep($mappingStep);
        }

        return $workflow;
    }

    /**
     * Extract output data from PortPHP result.
     */
    protected function extractOutputData(\Port\Result $portResult, array $outputArray): array
    {
        return [
            'rows_imported' => $portResult->getSuccessCount(),
            'rows_failed' => $portResult->getErrorCount(),
            'total_rows' => $portResult->getTotalProcessedCount(),
            'data' => $outputArray,
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
        if (($config->config['export_json'] ?? false) && ! empty($outputData['data'])) {
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

    /**
     * Apply filter rules to a row.
     *
     * Returns true to keep the row, false to skip it.
     */
    protected function applyFilters(array $row, array $filters): bool
    {
        // Check required columns (must not be empty)
        if (! empty($filters['required_columns'])) {
            foreach ($filters['required_columns'] as $column) {
                if (empty($row[$column] ?? null)) {
                    return false; // Skip row if required column is empty
                }
            }
        }

        // Check minimum values
        if (! empty($filters['min_values'])) {
            foreach ($filters['min_values'] as $column => $minValue) {
                $value = $row[$column] ?? null;
                if ($value !== null && is_numeric($value) && $value < $minValue) {
                    return false; // Skip row if value below minimum
                }
            }
        }

        // Check maximum values
        if (! empty($filters['max_values'])) {
            foreach ($filters['max_values'] as $column => $maxValue) {
                $value = $row[$column] ?? null;
                if ($value !== null && is_numeric($value) && $value > $maxValue) {
                    return false; // Skip row if value above maximum
                }
            }
        }

        // Check allowed values (whitelist)
        if (! empty($filters['allowed_values'])) {
            foreach ($filters['allowed_values'] as $column => $allowedValues) {
                $value = $row[$column] ?? null;
                if ($value !== null && ! in_array($value, $allowedValues, true)) {
                    return false; // Skip row if value not in whitelist
                }
            }
        }

        // Custom filter callback
        if (! empty($filters['custom_filter']) && is_callable($filters['custom_filter'])) {
            return (bool) $filters['custom_filter']($row);
        }

        return true; // Keep row if all filters pass
    }

    /**
     * Apply transformations to a row.
     *
     * Returns the transformed row.
     */
    protected function applyTransformations(array $row, array $transformations): array
    {
        // Uppercase columns
        if (! empty($transformations['uppercase'])) {
            foreach ($transformations['uppercase'] as $column) {
                if (isset($row[$column])) {
                    $row[$column] = strtoupper($row[$column]);
                }
            }
        }

        // Lowercase columns
        if (! empty($transformations['lowercase'])) {
            foreach ($transformations['lowercase'] as $column) {
                if (isset($row[$column])) {
                    $row[$column] = strtolower($row[$column]);
                }
            }
        }

        // Trim whitespace
        if (! empty($transformations['trim'])) {
            foreach ($transformations['trim'] as $column) {
                if (isset($row[$column]) && is_string($row[$column])) {
                    $row[$column] = trim($row[$column]);
                }
            }
        }

        // Convert to integer
        if (! empty($transformations['integer'])) {
            foreach ($transformations['integer'] as $column) {
                if (isset($row[$column])) {
                    $row[$column] = (int) $row[$column];
                }
            }
        }

        // Convert to float
        if (! empty($transformations['float'])) {
            foreach ($transformations['float'] as $column) {
                if (isset($row[$column])) {
                    $row[$column] = (float) $row[$column];
                }
            }
        }

        // Convert to boolean
        if (! empty($transformations['boolean'])) {
            foreach ($transformations['boolean'] as $column) {
                if (isset($row[$column])) {
                    $value = strtolower($row[$column]);
                    $row[$column] = in_array($value, ['1', 'true', 'yes', 'on'], true);
                }
            }
        }

        // Custom mapping callback
        if (! empty($transformations['mapping']) && is_callable($transformations['mapping'])) {
            $row = $transformations['mapping']($row);
        }

        return $row;
    }
}
