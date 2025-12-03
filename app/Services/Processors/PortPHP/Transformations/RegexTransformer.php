<?php

namespace App\Services\Processors\PortPHP\Transformations;

use Illuminate\Support\Facades\Log;

/**
 * Regex-based data transformation utilities.
 *
 * Provides powerful pattern-based extraction, replacement, splitting, and matching
 * capabilities for data transformation pipelines.
 */
class RegexTransformer
{
    /**
     * Extract a single value using a regex pattern with capture groups.
     *
     * @param string $value The input value to extract from
     * @param string $pattern The regex pattern with capture groups
     * @param int $group The capture group number to extract (1-based)
     * @return string|null The extracted value or null if no match
     *
     * @example
     * extract('+639171234567', '/^(\+63|0)?(9\d{2})\d{7}$/', 2) → '917'
     * extract('john@company.com', '/@(.+)$/', 1) → 'company.com'
     */
    public function extract(string $value, string $pattern, int $group = 1): ?string
    {
        try {
            if (preg_match($pattern, $value, $matches)) {
                return $matches[$group] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Regex extraction failed', [
                'pattern' => $pattern,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Extract all matching values using a regex pattern with capture groups.
     *
     * @param string $value The input value to extract from
     * @param string $pattern The regex pattern with capture groups
     * @param int $group The capture group number to extract (1-based)
     * @return array Array of all matched values
     *
     * @example
     * extractAll('Hello #world #php #laravel', '/#(\w+)/', 1) → ['world', 'php', 'laravel']
     * extractAll('Phones: 09171234567, 09181234567', '/09(\d{9})/', 1) → ['171234567', '181234567']
     */
    public function extractAll(string $value, string $pattern, int $group = 1): array
    {
        try {
            if (preg_match_all($pattern, $value, $matches)) {
                return $matches[$group] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Regex extractAll failed', [
                'pattern' => $pattern,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Replace parts of a value using regex pattern and replacement string.
     *
     * Supports backreferences ($1, $2, etc.) for captured groups.
     *
     * @param string $value The input value to transform
     * @param string $pattern The regex pattern to match
     * @param string $replacement The replacement string (supports $1, $2, etc.)
     * @return string The transformed value
     *
     * @example
     * replace('12/25/2024', '/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$1-$2') → '2024-12-25'
     * replace('EMP-001234', '/^EMP-/', '') → '001234'
     * replace('  hello  world  ', '/\s+/', ' ') → ' hello world '
     */
    public function replace(string $value, string $pattern, string $replacement): string
    {
        try {
            $result = preg_replace($pattern, $replacement, $value);
            return $result ?? $value;
        } catch (\Exception $e) {
            Log::error('Regex replacement failed', [
                'pattern' => $pattern,
                'value' => $value,
                'replacement' => $replacement,
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }

    /**
     * Split a value into multiple parts using a regex pattern.
     *
     * @param string $value The input value to split
     * @param string $pattern The regex pattern to split on
     * @param int $limit Maximum number of splits (0 = no limit)
     * @return array Array of split parts
     *
     * @example
     * split('Juan Dela Cruz', '/\s+/') → ['Juan', 'Dela', 'Cruz']
     * split('apple,banana;orange|grape', '/[,;|]/', 0) → ['apple', 'banana', 'orange', 'grape']
     * split('John Doe', '/\s+/', 2) → ['John', 'Doe']
     */
    public function split(string $value, string $pattern, int $limit = -1): array
    {
        try {
            $result = preg_split($pattern, $value, $limit);
            return $result !== false ? array_filter($result) : [$value];
        } catch (\Exception $e) {
            Log::error('Regex split failed', [
                'pattern' => $pattern,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return [$value];
        }
    }

    /**
     * Apply a transformation configuration to a value.
     *
     * Dispatches to the appropriate method based on transformation type.
     *
     * @param mixed $value The input value
     * @param array $config Transformation configuration
     * @return mixed The transformed value
     *
     * @example
     * transform('+639171234567', ['type' => 'extract', 'pattern' => '/9(\d{2})/', 'group' => 1]) → '17'
     * transform('12/25/2024', ['type' => 'replace', 'pattern' => '/(\d{2})\/(\d{2})\/(\d{4})/', 'replacement' => '$3-$1-$2']) → '2024-12-25'
     */
    public function transform(mixed $value, array $config): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $type = $config['type'] ?? 'extract';
        $pattern = $config['pattern'] ?? null;

        if (!$pattern) {
            Log::warning('Regex transformation missing pattern', ['config' => $config]);
            return $value;
        }

        return match ($type) {
            'extract' => $this->extract($value, $pattern, $config['group'] ?? 1) ?? $value,
            'extract_all' => $this->extractAll($value, $pattern, $config['group'] ?? 1),
            'replace' => $this->replace($value, $pattern, $config['replacement'] ?? ''),
            'split' => $this->split($value, $pattern, $config['limit'] ?? -1),
            default => $value,
        };
    }

    /**
     * Apply transformations to a row of data.
     *
     * Processes each field according to its transformation config, optionally
     * creating new fields from split or extract_all operations.
     *
     * @param array $row The input row data
     * @param array $transformations Field-level transformation configs
     * @return array The transformed row
     *
     * @example
     * transformRow(
     *     ['phone' => '+639171234567', 'full_name' => 'Juan Dela Cruz'],
     *     [
     *         'phone' => ['type' => 'extract', 'pattern' => '/9(\d{2})/', 'group' => 1],
     *         'full_name' => ['type' => 'split', 'pattern' => '/\s+/', 'output_fields' => ['first_name', 'last_name']]
     *     ]
     * )
     * → ['phone' => '17', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz']
     */
    public function transformRow(array $row, array $transformations): array
    {
        foreach ($transformations as $field => $config) {
            if (!isset($row[$field])) {
                continue;
            }

            $value = $row[$field];
            $result = $this->transform($value, $config);

            // Handle split with output_fields
            if (($config['type'] ?? null) === 'split' && isset($config['output_fields'])) {
                $outputFields = $config['output_fields'];
                $parts = is_array($result) ? $result : [$result];

                foreach ($outputFields as $index => $outputField) {
                    $row[$outputField] = $parts[$index] ?? null;
                }

                // Remove original field if requested
                if ($config['remove_original'] ?? false) {
                    unset($row[$field]);
                }
            }
            // Handle extract_all with output format
            elseif (($config['type'] ?? null) === 'extract_all') {
                $outputFormat = $config['output'] ?? 'array';

                if ($outputFormat === 'comma_separated') {
                    $row[$field] = is_array($result) ? implode(',', $result) : $result;
                } elseif ($outputFormat === 'json') {
                    $row[$field] = is_array($result) ? json_encode($result) : $result;
                } else {
                    // Keep as array (default)
                    $row[$field] = $result;
                }
            }
            // Standard transformation (extract, replace)
            else {
                $row[$field] = $result;
            }
        }

        return $row;
    }
}
