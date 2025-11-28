<?php

declare(strict_types=1);

namespace App\Data\Api\Resources;

use App\Models\ProcessorExecution;
use Spatie\LaravelData\Data;

/**
 * ProcessorExecution API Resource DTO
 * 
 * Represents a processor execution in API responses.
 */
class ProcessorExecutionData extends Data
{
    public function __construct(
        public string $id,
        public string $processor_slug,
        public string $processor_name,
        public string $status,
        public int $duration_ms,
        public ?int $tokens_used = null,
        public ?int $cost_credits = null,
        public ?array $output_data = null,
        public ?string $error_message = null,
        public ?string $started_at = null,
        public ?string $completed_at = null,
    ) {}
    
    /**
     * Create from ProcessorExecution model.
     */
    public static function fromModel(ProcessorExecution $execution): self
    {
        return new self(
            id: $execution->id,
            processor_slug: $execution->relationLoaded('processor') 
                ? $execution->processor->slug 
                : 'unknown',
            processor_name: $execution->relationLoaded('processor') 
                ? $execution->processor->name 
                : 'Unknown',
            status: $execution->state::$name ?? 'pending',
            duration_ms: $execution->duration_ms,
            tokens_used: $execution->tokens_used,
            cost_credits: $execution->cost_credits,
            output_data: $execution->output_data,
            error_message: $execution->error_message,
            started_at: $execution->started_at?->toIso8601String(),
            completed_at: $execution->completed_at?->toIso8601String(),
        );
    }
}
