<?php

declare(strict_types=1);

namespace App\Data\Api\Resources;

use App\Models\DocumentJob;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * DocumentJob API Resource DTO
 *
 * Represents a document processing job in API responses.
 */
class DocumentJobData extends Data
{
    public function __construct(
        public string $id,
        public string $uuid,
        public string $status,
        public int $current_processor_index,
        public ?array $pipeline_instance = null,
        /** @var ProcessorExecutionData[] */
        public ?DataCollection $processor_executions = null,
        public ?string $error_log = null,
        public ?string $started_at = null,
        public ?string $completed_at = null,
    ) {}

    /**
     * Create from DocumentJob model.
     */
    public static function fromModel(DocumentJob $job): self
    {
        return new self(
            id: $job->id,
            uuid: $job->uuid,
            status: $job->state::$name ?? 'pending',
            current_processor_index: $job->current_processor_index,
            pipeline_instance: $job->pipeline_instance,
            processor_executions: $job->relationLoaded('processorExecutions')
                ? ProcessorExecutionData::collection($job->processorExecutions)
                : null,
            error_log: $job->error_log,
            started_at: $job->started_at?->toIso8601String(),
            completed_at: $job->completed_at?->toIso8601String(),
        );
    }
}
