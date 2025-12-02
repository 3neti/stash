<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PipelineProgress Model
 *
 * Tracks real-time progress of document processing through the pipeline.
 */
class PipelineProgress extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected $connection = 'tenant';

    protected $table = 'pipeline_progress';

    protected $fillable = [
        'job_id',
        'stage_count',
        'completed_stages',
        'percentage_complete',
        'current_stage',
        'status',
    ];

    protected $casts = [
        'stage_count' => 'integer',
        'completed_stages' => 'integer',
        'percentage_complete' => 'float',
    ];

    protected $attributes = [
        'stage_count' => 0,
        'completed_stages' => 0,
        'percentage_complete' => 0.0,
        'status' => 'pending',
    ];

    public function documentJob(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentJob::class, 'job_id');
    }

    public function updateProgress(int $completedStages, int $totalStages, string $currentStage, string $status): void
    {
        $percentage = $totalStages > 0 ? round(($completedStages / $totalStages) * 100, 2) : 0;

        $this->update([
            'stage_count' => $totalStages,
            'completed_stages' => $completedStages,
            'percentage_complete' => $percentage,
            'current_stage' => $currentStage,
            'status' => $status,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
