<?php

declare(strict_types=1);

namespace App\Processors;

use App\Contracts\Processors\ProcessorInterface;
use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Data\Processors\ProcessorResultData;
use App\Models\Document;
use Throwable;

/**
 * Abstract Processor
 *
 * Base class for all document processors.
 */
abstract class AbstractProcessor implements ProcessorInterface
{
    protected string $name;

    protected string $category;

    /**
     * Check if this processor can process the given document.
     * Override this method to add custom validation logic.
     */
    public function canProcess(Document $document): bool
    {
        // Default: accept all documents
        // Override in subclasses to add mime type, size checks, etc.
        return true;
    }

    /**
     * Get the processor name.
     */
    public function getName(): string
    {
        return $this->name ?? class_basename($this);
    }

    /**
     * Get the processor category.
     */
    public function getCategory(): string
    {
        return $this->category ?? 'custom';
    }

    /**
     * Main handler with error handling wrapper.
     */
    public function handle(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): ProcessorResultData {
        try {
            $output = $this->process($document, $config);

            // Extract tokens_used and cost_credits if present (snake_case from processors)
            $tokensUsed = $output['tokens_used'] ?? null;
            $costCredits = $output['cost_credits'] ?? null;

            return new ProcessorResultData(
                success: true,
                output: $output,
                tokensUsed: $tokensUsed,
                costCredits: $costCredits
            );
        } catch (Throwable $e) {
            return new ProcessorResultData(
                success: false,
                output: [],
                error: $e->getMessage()
            );
        }
    }

    /**
     * Process the document. Implement this in subclasses.
     *
     * @return array The processing output
     */
    abstract protected function process(
        Document $document,
        ProcessorConfigData $config
    ): array;
}
