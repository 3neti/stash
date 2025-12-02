<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Models\Document;
use App\Models\DocumentJob;
use App\Workflows\Activities\OcrActivity;
use App\Workflows\Activities\ClassificationActivity;
use App\Workflows\Activities\ExtractionActivity;
use App\Workflows\Activities\ValidationActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

/**
 * DocumentProcessingWorkflow
 *
 * A durable workflow that orchestrates document processing through multiple activities.
 * Based on Laravel Workflow (inspired by Temporal) - uses generator-based async/await pattern.
 *
 * NOTE: This is a proof-of-concept skeleton. Not yet integrated with existing pipeline.
 */
class DocumentProcessingWorkflow extends Workflow
{
    /**
     * Execute the document processing workflow.
     *
     * @param string $documentJobId The DocumentJob ULID
     * @param string $tenantId The Tenant ULID for context
     * @return \Generator
     */
    public function execute(string $documentJobId, string $tenantId)
    {
        // Laravel Workflow uses generator-based async/await (like Temporal)
        // Each `yield` creates a checkpoint - if workflow crashes, it resumes from last checkpoint

        // Activity 1: OCR Processing
        $ocrResult = yield ActivityStub::make(
            OcrActivity::class,
            $documentJobId,
            $tenantId
        );

        // Activity 2: Classification (depends on OCR output)
        $classificationResult = yield ActivityStub::make(
            ClassificationActivity::class,
            $documentJobId,
            $ocrResult,
            $tenantId
        );

        // Activity 3: Extraction (depends on OCR + Classification)
        $extractionResult = yield ActivityStub::make(
            ExtractionActivity::class,
            $documentJobId,
            $ocrResult,
            $classificationResult,
            $tenantId
        );

        // Activity 4: Validation (depends on all previous outputs)
        $validationResult = yield ActivityStub::make(
            ValidationActivity::class,
            $documentJobId,
            $extractionResult,
            $tenantId
        );

        return [
            'ocr' => $ocrResult,
            'classification' => $classificationResult,
            'extraction' => $extractionResult,
            'validation' => $validationResult,
        ];
    }
}
