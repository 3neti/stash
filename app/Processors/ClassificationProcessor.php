<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Document;
use OpenAI;
use OpenAI\Client as OpenAIClient;

/**
 * ClassificationProcessor uses OpenAI to categorize documents into predefined types.
 *
 * Expected config structure:
 * {
 *   "categories": ["invoice", "receipt", "contract", "other"],
 *   "model": "gpt-4o-mini",
 *   "temperature": 0.3,
 *   "min_confidence": 0.7
 * }
 *
 * Returns:
 * {
 *   "category": "invoice",
 *   "confidence": 0.95,
 *   "reasoning": "Document contains invoice number, amounts, and billing details",
 *   "tokens_used": 245
 * }
 */
class ClassificationProcessor extends AbstractProcessor
{
    protected string $name = 'OpenAI Classification';

    protected string $category = 'classification';

    /**
     * @var OpenAIClient|null OpenAI client instance
     */
    private $openai = null;

    /**
     * Process the document using OpenAI classification.
     *
     * @param  Document  $document  The document to classify
     * @param  ProcessorConfigData  $config  Processor configuration
     * @return array The classification result
     *
     * @throws \Exception If classification fails
     */
    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // Get extracted text from previous OCR step
        $text = $this->getExtractedText($document, $config);

        // Get categories from config
        $categories = $config->config['categories'] ?? [
            'invoice',
            'receipt',
            'contract',
            'purchase_order',
            'letter',
            'other',
        ];

        // Get model and parameters
        $model = $config->config['model'] ?? 'gpt-4o-mini';
        $temperature = $config->config['temperature'] ?? 0.3;
        $minConfidence = $config->config['min_confidence'] ?? 0.7;

        // Initialize OpenAI client with credentials (if not already set)
        if ($this->openai === null) {
            $this->initializeOpenAI($document);
        }

        // Build classification prompt
        $prompt = $this->buildPrompt($text, $categories);

        // Call OpenAI API
        $response = $this->openai->chat()->create([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a document classification expert. Analyze documents and categorize them accurately.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $temperature,
            'max_tokens' => 200,
            'response_format' => ['type' => 'json_object'],
        ]);

        // Parse the response
        $result = $this->parseResponse($response);

        // Validate confidence threshold
        if ($result['confidence'] < $minConfidence) {
            throw new \Exception(
                sprintf(
                    'Classification confidence %.2f is below minimum threshold %.2f',
                    $result['confidence'],
                    $minConfidence
                )
            );
        }

        // Validate category is in allowed list
        if (! in_array($result['category'], $categories, true)) {
            throw new \Exception(
                sprintf(
                    'Classified category "%s" is not in allowed categories: %s',
                    $result['category'],
                    implode(', ', $categories)
                )
            );
        }

        return [
            'category' => $result['category'],
            'confidence' => $result['confidence'],
            'reasoning' => $result['reasoning'],
            'tokens_used' => $response->usage->totalTokens,
            'model' => $model,
            'processor' => $this->name,
            'version' => '1.0.0',
            'categories_available' => $categories,
        ];
    }

    /**
     * Get extracted text from document metadata.
     *
     * @param  Document  $document  The document
     * @param  ProcessorConfigData  $config  Configuration
     * @return string The extracted text
     *
     * @throws \Exception If no text is available
     */
    private function getExtractedText(Document $document, ProcessorConfigData $config): string
    {
        // Get text from document metadata (set by previous OCR processor)
        if (isset($document->metadata['extracted_text'])) {
            return $document->metadata['extracted_text'];
        }

        // If no text available, we need OCR to run first
        throw new \Exception(
            'No extracted text found. OCR processor must run before classification.'
        );
    }

    /**
     * Build the classification prompt for OpenAI.
     *
     * @param  string  $text  The document text to classify
     * @param  array  $categories  Available categories
     * @return string The formatted prompt
     */
    private function buildPrompt(string $text, array $categories): string
    {
        $categoriesList = implode(', ', $categories);

        // Truncate text if too long (keep first 2000 chars for context)
        $truncatedText = strlen($text) > 2000
            ? substr($text, 0, 2000).'...[truncated]'
            : $text;

        return <<<PROMPT
Analyze the following document text and classify it into ONE of these categories:
{$categoriesList}

Document text:
{$truncatedText}

Respond in JSON format with exactly these fields:
{
  "category": "one_of_the_categories",
  "confidence": 0.95,
  "reasoning": "Brief explanation of why you chose this category"
}

IMPORTANT:
- Choose ONLY from the provided categories
- Confidence must be between 0 and 1
- Reasoning should be 1-2 sentences maximum
PROMPT;
    }

    /**
     * Parse OpenAI response and extract classification result.
     *
     * @param  \OpenAI\Responses\Chat\CreateResponse  $response  OpenAI API response
     * @return array{category: string, confidence: float, reasoning: string} Parsed result
     *
     * @throws \Exception If parsing fails
     */
    private function parseResponse($response): array
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

        // Validate required fields
        $requiredFields = ['category', 'confidence', 'reasoning'];
        foreach ($requiredFields as $field) {
            if (! isset($decoded[$field])) {
                throw new \Exception(
                    sprintf('Missing required field "%s" in OpenAI response', $field)
                );
            }
        }

        // Normalize confidence to float
        $confidence = (float) $decoded['confidence'];
        if ($confidence < 0 || $confidence > 1) {
            throw new \Exception(
                sprintf('Invalid confidence value: %.2f (must be between 0 and 1)', $confidence)
            );
        }

        return [
            'category' => trim($decoded['category']),
            'confidence' => $confidence,
            'reasoning' => trim($decoded['reasoning']),
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
}
