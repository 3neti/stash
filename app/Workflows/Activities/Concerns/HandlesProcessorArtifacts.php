<?php

declare(strict_types=1);

namespace App\Workflows\Activities\Concerns;

use App\Data\Processors\ProcessorResultData;
use App\Models\Document;
use App\Models\ProcessorExecution;

/**
 * Handles Processor Artifacts Trait
 *
 * Provides functionality to attach artifact files from ProcessorResultData
 * to ProcessorExecution models using Media Library.
 *
 * Usage: Add to any Workflow Activity that processes documents.
 */
trait HandlesProcessorArtifacts
{
    /**
     * Attach artifact files from processor result to execution.
     *
     * @param  ProcessorExecution  $execution  The execution to attach artifacts to
     * @param  ProcessorResultData  $result  The processor result containing artifact files
     * @param  Document  $document  The document being processed
     * @param  string  $processorType  The processor type (ocr, classification, extraction, etc.)
     * @return int Number of artifacts attached
     */
    protected function attachResultArtifacts(
        ProcessorExecution $execution,
        ProcessorResultData $result,
        Document $document,
        string $processorType
    ): int {
        if (empty($result->artifactFiles)) {
            return 0;
        }

        $attachedCount = 0;

        foreach ($result->artifactFiles as $collection => $filePath) {
            try {
                $execution->attachArtifact($filePath, $collection, [
                    'processor_type' => $processorType,
                    'document_id' => $document->id,
                    'original_filename' => basename($filePath),
                ]);
                $attachedCount++;
            } catch (\Throwable $e) {
                // Log error but don't fail the activity
                // Artifact attachment is optional and shouldn't break processing
                \Log::warning('Failed to attach artifact', [
                    'execution_id' => $execution->id,
                    'collection' => $collection,
                    'file_path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $attachedCount;
    }
}
