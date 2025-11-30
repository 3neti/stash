<?php

declare(strict_types=1);

namespace App\Contracts\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Data\Processors\ProcessorResultData;
use App\Models\Document;

/**
 * Processor Interface
 *
 * Contract that all document processors must implement.
 */
interface ProcessorInterface
{
    /**
     * Process the document with given configuration and context.
     */
    public function handle(
        Document $document,
        ProcessorConfigData $config,
        ProcessorContextData $context
    ): ProcessorResultData;

    /**
     * Check if this processor can process the given document.
     */
    public function canProcess(Document $document): bool;

    /**
     * Get the processor name.
     */
    public function getName(): string;

    /**
     * Get the processor category.
     */
    public function getCategory(): string;
}
