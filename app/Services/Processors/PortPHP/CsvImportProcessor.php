<?php

declare(strict_types=1);

namespace App\Services\Processors\PortPHP;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Document;
use Port\Csv\CsvReader;
use Port\Steps\StepAggregator;
use Port\Writer\ArrayWriter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\Validation\CustomRuleRegistry;
use App\Tenancy\TenantContext;
use App\Services\Processors\PortPHP\Transformations\RegexTransformer;

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
 * - regex_transformations: Array of regex-based field transformations (applied FIRST)
 *   Example: ['phone' => ['type' => 'extract', 'pattern' => '/9(\d{2})/', 'group' => 1]]
 * - uppercase: Array of columns to uppercase
 * - lowercase: Array of columns to lowercase
 * - trim: Array of columns to trim whitespace
 * - integer: Array of columns to convert to integers
 * - float: Array of columns to convert to floats
 * - boolean: Array of columns to convert to booleans
 * - mapping: Custom callback mapping function
 *
 * Filter options (DEPRECATED - Use validation_rules instead):
 * - required_columns: Array of columns that must not be empty
 * - min_values: Object with column => minimum value constraints
 * - max_values: Object with column => maximum value constraints
 * - allowed_values: Object with column => array of allowed values
 * - custom_filter: Custom callback filter function
 *
 * Validation Rules (Recommended - Laravel Validation):
 * - validation_rules: Array of Laravel validation rules per column
 *   Example: ['email' => ['required', 'email'], 'salary' => ['required', 'numeric', 'min:0']]
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
     * Override handle() to apply post-processing filters and transformations.
     */
    public function handle(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): \App\Data\Processors\ProcessorResultData {
        // Call parent to run PortPHP workflow
        $result = parent::handle($document, $config, $context);

        // Apply filters and transformations to output data
        if ($result->success && !empty($result->output['data'])) {
            $filters = $config->config['filters'] ?? [];
            $transformations = $config->config['transformations'] ?? [];

            $processedData = [];
            $filteredCount = 0;

            foreach ($result->output['data'] as $row) {
                // Apply transformations FIRST (clean data before validation)
                if (!empty($transformations)) {
                    $row = $this->applyTransformations($row, $transformations);
                }

                // Apply date conversions
                $dateColumns = $config->config['date_columns'] ?? [];
                if (!empty($dateColumns)) {
                    $dateFormat = $config->config['date_format'] ?? 'Y-m-d';
                    $row = $this->convertDateColumns($row, $dateColumns, $dateFormat);
                }

                // Apply validation/filters AFTER transformations
                if (!empty($filters) && !$this->applyFilters($row, $filters, $document)) {
                    $filteredCount++;
                    continue; // Skip this row
                }

                $processedData[] = $row;
            }

            // Create new output array with updated data
            $newOutput = $result->output;
            $newOutput['data'] = $processedData;
            $newOutput['rows_imported'] = count($processedData);
            $newOutput['rows_filtered'] = $filteredCount;
            $newOutput['rows_failed'] = ($result->output['rows_failed'] ?? 0) + $filteredCount;

            // Return new ProcessorResultData with modified output
            return new \App\Data\Processors\ProcessorResultData(
                success: $result->success,
                output: $newOutput,
                error: $result->error,
                metadata: $result->metadata,
                artifactFiles: $result->artifactFiles
            );
        }

        return $result;
    }

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

        // Note: Date conversion is handled in post-processing
        // ValueConverterStep expects object properties, not array keys

        return $workflow;
    }

    /**
     * Extract output data from PortPHP result.
     */
    protected function extractOutputData(\Port\Result $portResult, array $outputArray): array
    {
        // Note: Filters and transformations are applied post-processing
        // to avoid complexity with PortPHP's middleware Step pattern
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
     * Apply filter rules to a row using Laravel validation.
     *
     * Returns true to keep the row, false to skip it.
     */
    protected function applyFilters(array $row, array $filters, Document $document): bool
    {
        // Use Laravel validation if rules provided (recommended)
        if (!empty($filters['validation_rules'])) {
            // Get tenant ID from current tenant context
            $tenant = TenantContext::current();
            
            if (!$tenant) {
                throw new \RuntimeException('No tenant context initialized for validation');
            }
            
            // Detect locale from document's campaign settings, tenant settings, or default to 'en'
            $locale = $this->detectLocale($document, $tenant);
            
            // First, validate expression-based custom rules with full row context
            // (Laravel's validator closures don't have access to full row)
            if (!$this->validateCustomExpressionRules($row, $filters['validation_rules'], $tenant->id, $locale)) {
                return false; // Skip if expression validation fails
            }
            
            // Then process and validate with Laravel
            $rules = $this->processValidationRules($filters['validation_rules'], $tenant->id, $locale, $row);
            
            $validator = Validator::make($row, $rules);
            
            if ($validator->fails()) {
                // Optionally log validation errors for debugging
                // Log::debug('CSV row validation failed', [
                //     'row' => $row,
                //     'errors' => $validator->errors()->all(),
                // ]);
                return false; // Skip invalid row
            }
            
            return true; // Keep valid row
        }

        // Legacy filters (backward compatibility) - DEPRECATED
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
     *
     * Transformation order:
     * 1. Regex transformations (extract, replace, split, extract_all)
     * 2. Simple transformations (uppercase, lowercase, trim, type conversions)
     * 3. Custom mapping callback
     */
    public function applyTransformations(array $row, array $transformations): array
    {
        // 1. Apply regex transformations FIRST (most powerful, can create new fields)
        if (!empty($transformations['regex_transformations'])) {
            $regexTransformer = new RegexTransformer();
            $row = $regexTransformer->transformRow($row, $transformations['regex_transformations']);
        }

        // 2. Simple transformations
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

    /**
     * Validate custom expression rules with full row context.
     *
     * Expression rules need access to all fields in the row, which Laravel's
     * validator closures don't provide, so we validate them separately.
     */
    protected function validateCustomExpressionRules(array $row, array $rules, string $tenantId, ?string $locale = null): bool
    {
        // Load tenant-specific custom rules
        CustomRuleRegistry::loadTenantRules($tenantId);

        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                // Only process expression-based custom rules
                if (is_string($rule) && str_starts_with($rule, 'custom:')) {
                    $ruleName = substr($rule, 7);
                    $customRule = CustomRuleRegistry::get($ruleName);

                    // Only validate if it's an expression rule
                    if ($customRule && $customRule->type === 'expression') {
                        $fieldValue = $row[$field] ?? null;
                        
                        // Pass full row as context for multi-field expressions
                        if (!$customRule->validate($fieldValue, $row)) {
                            // Log the localized error message for debugging
                            $context = array_merge($row, [
                                'attribute' => $field,
                                'value' => $fieldValue,
                            ]);
                            $errorMessage = $customRule->getErrorMessage($locale, $context);
                            \Log::debug('CSV row validation failed (expression rule)', [
                                'field' => $field,
                                'rule' => $ruleName,
                                'value' => $fieldValue,
                                'row' => $row,
                                'error' => $errorMessage,
                            ]);
                            return false; // Expression validation failed
                        }
                    }
                }
            }
        }

        return true; // All expression validations passed
    }

    /**
     * Process validation rules to handle special cases.
     *
     * Converts string-based custom rules (e.g., 'in_ci:value1,value2', 'custom:rule_name') into closures.
     */
    protected function processValidationRules(array $rules, string $tenantId, ?string $locale = null, array $row = []): array
    {
        // Load tenant-specific custom rules
        CustomRuleRegistry::loadTenantRules($tenantId);

        $processed = [];

        foreach ($rules as $field => $fieldRules) {
            $processedFieldRules = [];

            foreach ($fieldRules as $rule) {
                // Handle custom validation rules: 'custom:rule_name'
                if (is_string($rule) && str_starts_with($rule, 'custom:')) {
                    $ruleName = substr($rule, 7);
                    $customRule = CustomRuleRegistry::get($ruleName);

                    if ($customRule) {
                        // Skip expression rules here (they're validated separately with full row context)
                        if ($customRule->type !== 'expression') {
                            $processedFieldRules[] = function ($attribute, $value, $fail) use ($customRule, $locale, $row) {
                                // Regex and callback rules only need the field value
                                if (!$customRule->validate($value)) {
                                    // Get localized error message with context
                                    $context = array_merge($row, [
                                        'attribute' => $attribute,
                                        'value' => $value,
                                    ]);
                                    $fail($customRule->getErrorMessage($locale, $context));
                                }
                            };
                        }
                    } else {
                        // Rule not found - log warning and skip
                        \Log::warning('Custom validation rule not found', [
                            'rule_name' => $ruleName,
                            'field' => $field,
                        ]);
                    }
                }
                // Handle custom 'in_ci' (case-insensitive in) rule
                elseif (is_string($rule) && str_starts_with($rule, 'in_ci:')) {
                    $values = explode(',', substr($rule, 6));
                    $processedFieldRules[] = function ($attribute, $value, $fail) use ($values) {
                        $valueLower = strtolower($value);
                        $valuesLower = array_map('strtolower', $values);
                        if (!in_array($valueLower, $valuesLower)) {
                            $fail('The ' . $attribute . ' must be one of: ' . implode(', ', $values));
                        }
                    };
                } else {
                    $processedFieldRules[] = $rule;
                }
            }

            $processed[$field] = $processedFieldRules;
        }

        return $processed;
    }

    /**
     * Convert date columns to DateTime objects or formatted strings.
     */
    protected function convertDateColumns(array $row, array $dateColumns, string $dateFormat): array
    {
        foreach ($dateColumns as $column) {
            if (isset($row[$column]) && !empty($row[$column])) {
                try {
                    // Try to parse the date string
                    $date = \DateTime::createFromFormat($dateFormat, $row[$column]);
                    
                    if ($date === false) {
                        // Try standard strtotime parsing
                        $date = new \DateTime($row[$column]);
                    }
                    
                    // Keep as formatted string (or convert to ISO 8601)
                    $row[$column] = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }
        }

        return $row;
    }

    /**
     * Detect locale from campaign settings, tenant settings, or default.
     *
     * Priority:
     * 1. Campaign settings['locale']
     * 2. Tenant settings['locale']
     * 3. Default: 'en'
     */
    protected function detectLocale(Document $document, $tenant): string
    {
        // 1. Check campaign settings
        if ($document->campaign && isset($document->campaign->settings['locale'])) {
            return $document->campaign->settings['locale'];
        }

        // 2. Check tenant settings
        if (isset($tenant->settings['locale'])) {
            return $tenant->settings['locale'];
        }

        // 3. Default to English
        return 'en';
    }
}
