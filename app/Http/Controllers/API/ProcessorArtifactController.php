<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ProcessorExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Processor Artifact API Controller
 *
 * Provides endpoints to retrieve processor-generated artifacts (binary files).
 */
class ProcessorArtifactController extends Controller
{
    /**
     * List all artifacts for a document across all processor executions.
     *
     * GET /api/documents/{document}/artifacts
     */
    public function documentArtifacts(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $artifacts = [];

        // Get all processor executions for this document's job
        $executions = $document->documentJob
            ->processorExecutions()
            ->with(['processor', 'media'])
            ->get();

        foreach ($executions as $execution) {
            foreach ($execution->media as $media) {
                $artifacts[] = [
                    'id' => $media->id,
                    'execution_id' => $execution->id,
                    'processor' => [
                        'id' => $execution->processor->id,
                        'name' => $execution->processor->name,
                        'slug' => $execution->processor->slug,
                    ],
                    'collection' => $media->collection_name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'custom_properties' => $media->custom_properties,
                    'download_url' => route('api.processor-executions.artifacts.download', [
                        'execution' => $execution->id,
                        'media' => $media->id,
                    ]),
                    'created_at' => $media->created_at->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'document_id' => $document->id,
            'job_id' => $document->documentJob->id,
            'total_artifacts' => count($artifacts),
            'artifacts' => $artifacts,
        ]);
    }

    /**
     * List artifacts for a specific processor execution by collection.
     *
     * GET /api/processor-executions/{execution}/artifacts/{collection}
     */
    public function executionArtifactsByCollection(
        ProcessorExecution $execution,
        string $collection
    ): JsonResponse {
        $this->authorize('view', $execution->documentJob->document);

        $media = $execution->getMedia($collection);

        $artifacts = $media->map(function ($item) use ($execution) {
            return [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'custom_properties' => $item->custom_properties,
                'download_url' => route('api.processor-executions.artifacts.download', [
                    'execution' => $execution->id,
                    'media' => $item->id,
                ]),
                'created_at' => $item->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'execution_id' => $execution->id,
            'processor' => $execution->processor->name,
            'collection' => $collection,
            'total' => $artifacts->count(),
            'artifacts' => $artifacts,
        ]);
    }

    /**
     * Download a specific artifact file.
     *
     * GET /api/processor-executions/{execution}/artifacts/{media}/download
     */
    public function download(ProcessorExecution $execution, int $mediaId): Response
    {
        $this->authorize('view', $execution->documentJob->document);

        $media = $execution->media()->findOrFail($mediaId);

        return response()->download($media->getPath(), $media->file_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    /**
     * Get artifact metadata without downloading.
     *
     * GET /api/processor-executions/{execution}/artifacts/{media}
     */
    public function show(ProcessorExecution $execution, int $mediaId): JsonResponse
    {
        $this->authorize('view', $execution->documentJob->document);

        $media = $execution->media()->findOrFail($mediaId);

        return response()->json([
            'id' => $media->id,
            'execution_id' => $execution->id,
            'processor' => [
                'id' => $execution->processor->id,
                'name' => $execution->processor->name,
                'slug' => $execution->processor->slug,
            ],
            'collection' => $media->collection_name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'custom_properties' => $media->custom_properties,
            'conversions' => $media->getGeneratedConversions(),
            'url' => $media->getUrl(),
            'download_url' => route('api.processor-executions.artifacts.download', [
                'execution' => $execution->id,
                'media' => $media->id,
            ]),
            'created_at' => $media->created_at->toIso8601String(),
            'updated_at' => $media->updated_at->toIso8601String(),
        ]);
    }
}
