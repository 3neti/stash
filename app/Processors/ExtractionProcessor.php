<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use OpenAI;

/**
 * ExtractionProcessor uses OpenAI to extract structured fields from documents.
 *
 * Expected config structure:
 * {
 *   "schema": {
 *     "invoice": ["invoice_number", "date", "vendor", "total_amount", "line_items"],
 *     "receipt": ["merchant", "date", "items", "total"]
 *   },
 *   "model": "gpt-4o-mini",
 *   "temperature": 0.1
 * }
 *
 * Returns:
 * {
 *   "fields": {
 *     "invoice_number": {"value": "INV-2024-001", "confidence": 0.98},
 *     "date": {"value": "2024-01-15", "confidence": 0.95},
 *     "vendor": {"value": "Acme Corp", "confidence": 0.97},
 *     "total_amount": {"value": 1250.00, "confidence": 0.99}
 *   },
 *   "tokens_used": 450
 * }
 */
class ExtractionProcessor extends AbstractProcessor
{
    protected string $name = 'OpenAI Extraction';

    protected string $category = 'extraction';

    /**
     * @var mixed OpenAI client instance
     */
    private $openai = null;

    /**
     * Process the document and extract structured fields.
     *
     * @param  Document  $document  The document to extract from
     * @param  ProcessorConfigData  $config  Processor configuration
     * @return array The extraction result
     *
     * @throws \Exception If extraction fails
     */
    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Get extracted text and category
        $text = $this->getExtractedText($document);
        $category = $this->getDocumentCategory($document);

        // Get extraction schema for this document category
        $schema = $this->getSchema($config, $category);

        if (empty($schema)) {
            throw new \Exception(
                sprintf('No extraction schema defined for category: %s', $category)
            );
        }

        // Get model and parameters
        $model = $config->config['model'] ?? 'gpt-4o-mini';
        $temperature = $config->config['temperature'] ?? 0.1; // Lower for extraction accuracy

        // Initialize OpenAI client with credentials (if not already set)
        if ($this->openai === null) {
            $this->initializeOpenAI($document);
        }

        // Build extraction prompt
        $prompt = $this->buildPrompt($text, $category, $schema);

        // Call OpenAI API
        $response = $this->openai->chat()->create([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a document data extraction expert. Extract structured information accurately from documents.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $temperature,
            'max_tokens' => 800,
            'response_format' => ['type' => 'json_object'],
        ]);

        // Parse the response
        $result = $this->parseResponse($response, $schema);

        return [
            'category' => $category,
            'fields' => $result['fields'],
            'tokens_used' => $response->usage->totalTokens,
            'model' => $model,
            'processor' => $this->name,
            'version' => '1.0.0',
            'schema_used' => $schema,
        ];
    }

    /**
     * Get extracted text from document metadata.
     *
     * @param  Document  $document  The document
     * @return string The extracted text
     *
     * @throws \Exception If no text is available
     */
    private function getExtractedText(Document $document): string
    {
        // Get text from document metadata (set by previous OCR processor)
        if (isset($document->metadata['extracted_text'])) {
            return $document->metadata['extracted_text'];
        }

        // If no text available, we need OCR to run first
        throw new \Exception(
            'No extracted text found. OCR processor must run before extraction.'
        );
    }

    /**
     * Get document category from metadata.
     *
     * @param  Document  $document  The document
     * @return string The document category
     *
     * @throws \Exception If no category is available
     */
    private function getDocumentCategory(Document $document): string
    {
        // Get category from document metadata (set by previous classification processor)
        if (isset($document->metadata['category'])) {
            return $document->metadata['category'];
        }

        // Default to 'unknown' if no classification
        return 'unknown';
    }

    /**
     * Get extraction schema for document category.
     *
     * @param  ProcessorConfigData  $config  Configuration
     * @param  string  $category  Document category
     * @return array Field names to extract
     */
    private function getSchema(ProcessorConfigData $config, string $category): array
    {
        $schemas = $config->config['schema'] ?? [];

        // Return schema for this category
        if (isset($schemas[$category])) {
            return $schemas[$category];
        }

        // Fallback: generic schema for unknown categories
        return ['document_type', 'date', 'description', 'key_information'];
    }

    /**
     * Build the extraction prompt for OpenAI.
     *
     * @param  string  $text  The document text
     * @param  string  $category  Document category
     * @param  array  $schema  Field names to extract
     * @return string The formatted prompt
     */
    private function buildPrompt(string $text, string $category, array $schema): string
    {
        $fieldsList = implode(', ', $schema);

        // Truncate text if too long (keep first 3000 chars for context)
        $truncatedText = strlen($text) > 3000
            ? substr($text, 0, 3000).'...[truncated]'
            : $text;

        return <<<PROMPT
Extract the following fields from this {$category} document:
{$fieldsList}

Document text:
{$truncatedText}

For each field, provide:
1. The extracted value (or null if not found)
2. A confidence score (0.0 to 1.0)

Respond in JSON format:
{
  "fields": {
    "field_name": {
      "value": "extracted value or null",
      "confidence": 0.95
    }
  }
}

IMPORTANT:
- Extract ONLY the requested fields
- Set value to null if field is not found in the document
- Confidence should reflect how certain you are about the extraction
- For dates, use ISO 8601 format (YYYY-MM-DD)
- For amounts, use numeric values without currency symbols
- Be precise and extract exact values from the document
PROMPT;
    }

    /**
     * Parse OpenAI response and extract fields.
     *
     * @param  mixed  $response  OpenAI API response
     * @param  array  $schema  Expected field names
     * @return array Parsed extraction result
     *
     * @throws \Exception If parsing fails
     */
    private function parseResponse($response, array $schema): array
    {
        $content = $response->choices[0]->message->content ?? '';

        if (empty($content)) {
            throw new \Exception('Empty response from OpenAI');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                sprintf('Failed to parse OpenAI response as JSON: %s', json_last_error_msg())
            );
        }

        // Validate structure
        if (! isset($decoded['fields']) || ! is_array($decoded['fields'])) {
            throw new \Exception('Invalid response structure: missing or invalid "fields" key');
        }

        // Validate and normalize each field
        $normalizedFields = [];
        foreach ($schema as $fieldName) {
            if (isset($decoded['fields'][$fieldName])) {
                $field = $decoded['fields'][$fieldName];

                // Ensure field has value and confidence
                if (! array_key_exists('value', $field)) {
                    throw new \Exception(
                        sprintf('Field "%s" is missing "value" property', $fieldName)
                    );
                }

                $confidence = $field['confidence'] ?? 0.0;
                $confidence = (float) $confidence;

                if ($confidence < 0 || $confidence > 1) {
                    throw new \Exception(
                        sprintf('Invalid confidence value for field "%s": %.2f', $fieldName, $confidence)
                    );
                }

                $normalizedFields[$fieldName] = [
                    'value' => $field['value'],
                    'confidence' => $confidence,
                ];
            } else {
                // Field not found in response, mark as null with 0 confidence
                $normalizedFields[$fieldName] = [
                    'value' => null,
                    'confidence' => 0.0,
                ];
            }
        }

        return [
            'fields' => $normalizedFields,
        ];
    }

    /**
     * Initialize OpenAI client with credentials from campaign hierarchy.
     *
     * @param  Document  $document  The document to get campaign context from
     *
     * @throws \Exception If API key is not configured
     */
    private function initializeOpenAI(Document $document): void
    {
        // Get API key from campaign credentials, fall back to system config
        $apiKey = $this->resolveApiKey($document);

        if (empty($apiKey)) {
            throw new \Exception(
                'OpenAI API key not configured. Set OPENAI_API_KEY in .env or configure in campaign credentials.'
            );
        }

        $this->openai = OpenAI::client($apiKey);
    }

    /**
     * Resolve OpenAI API key from campaign credentials or system config.
     *
     * Priority:
     * 1. Campaign credentials (tenant-specific)
     * 2. System config (.env)
     *
     * @param  Document  $document  The document
     * @return string|null The API key or null if not found
     */
    private function resolveApiKey(Document $document): ?string
    {
        // Try campaign credentials first
        if ($document->campaign && ! empty($document->campaign->credentials)) {
            $credentials = is_array($document->campaign->credentials)
                ? $document->campaign->credentials
                : json_decode($document->campaign->credentials, true);

            if (isset($credentials['openai']['api_key'])) {
                return $credentials['openai']['api_key'];
            }
        }

        // Fall back to system config
        return config('services.openai.api_key') ?? env('OPENAI_API_KEY');
    }

    /**
     * Set OpenAI client (for testing).
     *
     * @param  mixed  $client  The OpenAI client instance (or mock)
     */
    public function setOpenAIClient($client): void
    {
        $this->openai = $client;
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entities' => ['type' => 'object'],
                'tables' => ['type' => 'array', 'items' => ['type' => 'object']],
                'extraction_confidence' => ['type' => 'number'],
            ],
            'required' => ['entities'],
        ];
    }
}
