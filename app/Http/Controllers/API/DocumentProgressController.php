<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\PipelineProgress;
use Illuminate\Http\JsonResponse;

class DocumentProgressController extends Controller
{
    /**
     * Get progress of document processing.
     *
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        $document = Document::where('uuid', $uuid)->firstOrFail();

        // Get the current or most recent job for this document
        $job = $document->documentJobs()
            ->orderByDesc('created_at')
            ->first();

        if (!$job) {
            return response()->json([
                'status' => 'no_job',
                'percentage_complete' => 0,
                'stage_count' => 0,
                'completed_stages' => 0,
                'current_stage' => null,
            ]);
        }

        // Get progress record
        $progress = PipelineProgress::where('job_id', $job->id)->first();

        if (!$progress) {
            return response()->json([
                'status' => 'pending',
                'percentage_complete' => 0,
                'stage_count' => count($job->pipeline_instance['processors'] ?? []),
                'completed_stages' => 0,
                'current_stage' => null,
            ]);
        }

        return response()->json([
            'status' => $progress->status,
            'percentage_complete' => $progress->percentage_complete,
            'stage_count' => $progress->stage_count,
            'completed_stages' => $progress->completed_stages,
            'current_stage' => $progress->current_stage,
            'updated_at' => $progress->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Get metrics of document processing.
     *
     * @return JsonResponse
     */
    public function metrics(string $uuid): JsonResponse
    {
        $document = Document::where('uuid', $uuid)->firstOrFail();

        // Get the current or most recent job for this document
        $job = $document->documentJobs()
            ->orderByDesc('created_at')
            ->first();

        if (!$job) {
            return response()->json([], 404);
        }

        // Get all processor executions for this job with processor details
        $executions = $job->processorExecutions()
            ->with('processor')
            ->orderBy('created_at')
            ->get()
            ->map(function ($execution) {
                return [
                    'processor_id' => $execution->processor_id,
                    'processor' => [
                        'name' => $execution->processor->name ?? 'Unknown',
                        'category' => $execution->processor->category ?? 'Unknown',
                    ],
                    'duration_ms' => $execution->duration_ms,
                    'status' => $execution->state->value ?? 'unknown',
                    'completed_at' => $execution->completed_at?->toIso8601String(),
                ];
            });

        return response()->json($executions);
    }
}
