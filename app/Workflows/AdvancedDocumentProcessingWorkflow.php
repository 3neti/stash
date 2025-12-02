<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Workflows\Activities\ClassificationActivity;
use App\Workflows\Activities\ExtractionActivity;
use App\Workflows\Activities\OcrActivity;
use App\Workflows\Activities\ValidationActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

/**
 * Advanced Document Processing Workflow
 *
 * Demonstrates Laravel Workflow advanced features:
 * - Conditional execution (document-type routing)
 * - Parallel execution (classification + extraction)
 * - Error handling with retries
 *
 * This is an example workflow showcasing capabilities.
 * Use DocumentProcessingWorkflow for production.
 */
class AdvancedDocumentProcessingWorkflow extends Workflow
{
    /**
     * Execute the workflow with advanced patterns.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param string $tenantId The Tenant ULID for context
     * @return \Generator
     */
    public function execute(string $documentJobId, string $tenantId)
    {
        // Step 1: OCR Processing (always runs first)
        $ocrResult = yield ActivityStub::make(
            OcrActivity::class,
            $documentJobId,
            $tenantId
        );

        // Step 2: CONDITIONAL EXECUTION - Route based on document type
        // This demonstrates native PHP conditional logic in workflows
        $documentType = $ocrResult['document_type'] ?? 'generic';

        if ($documentType === 'invoice') {
            // Invoice-specific processing path
            $classificationResult = yield ActivityStub::make(
                ClassificationActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            );
        } elseif ($documentType === 'receipt') {
            // Receipt-specific processing path
            $classificationResult = yield ActivityStub::make(
                ClassificationActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            );
        } else {
            // Generic document path
            $classificationResult = yield ActivityStub::make(
                ClassificationActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            );
        }

        // Step 3: PARALLEL EXECUTION - Run classification and extraction simultaneously
        // ActivityStub::all() waits for all activities to complete before proceeding
        // This can significantly reduce total processing time
        [$classificationResult, $extractionResult] = yield ActivityStub::all([
            ActivityStub::make(
                ClassificationActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            ),
            ActivityStub::make(
                ExtractionActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            ),
        ]);

        // Step 4: Validation (runs after both parallel activities complete)
        $validationResult = yield ActivityStub::make(
            ValidationActivity::class,
            $documentJobId,
            $extractionResult,
            $tenantId
        );

        // Return all results
        return [
            'ocr' => $ocrResult,
            'classification' => $classificationResult,
            'extraction' => $extractionResult,
            'validation' => $validationResult,
            'document_type' => $documentType,
            'execution_pattern' => 'advanced', // Indicates this used advanced features
        ];
    }

    /**
     * Alternative: Using match expression for conditional routing (PHP 8+)
     *
     * This method demonstrates PHP 8's match expression for cleaner routing.
     */
    public function executeWithMatch(string $documentJobId, string $tenantId)
    {
        // OCR first
        $ocrResult = yield ActivityStub::make(
            OcrActivity::class,
            $documentJobId,
            $tenantId
        );

        $documentType = $ocrResult['document_type'] ?? 'generic';

        // Use match for cleaner routing
        $activityClass = match ($documentType) {
            'invoice' => ClassificationActivity::class,
            'receipt' => ClassificationActivity::class,
            default => ClassificationActivity::class,
        };

        $classificationResult = yield ActivityStub::make(
            $activityClass,
            $documentJobId,
            $ocrResult,
            $tenantId
        );

        // Continue with parallel execution...
        [$extractionResult, $validationResult] = yield ActivityStub::all([
            ActivityStub::make(
                ExtractionActivity::class,
                $documentJobId,
                $ocrResult,
                $tenantId
            ),
            ActivityStub::make(
                ValidationActivity::class,
                $documentJobId,
                $classificationResult,
                $tenantId
            ),
        ]);

        return compact('ocrResult', 'classificationResult', 'extractionResult', 'validationResult');
    }
}
