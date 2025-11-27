<?php

declare(strict_types=1);

namespace App\Models;

use App\States\ProcessorExecution\ProcessorExecutionState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * ProcessorExecution Model
 * 
 * Tracks individual processor invocations with metrics.
 */
class ProcessorExecution extends Model
{
    use BelongsToTenant, HasFactory, HasStates;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'job_id',
        'processor_id',
        'input_data',
        'output_data',
        'config',
        'status',
        'duration_ms',
        'error_message',
        'tokens_used',
        'cost_credits',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'config' => 'array',
        'duration_ms' => 'integer',
        'tokens_used' => 'integer',
        'cost_credits' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => ProcessorExecutionState::class,
    ];

    protected $attributes = [
        'status' => 'pending',
        'tokens_used' => 0,
        'cost_credits' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ProcessorExecution $execution) {
            if (empty($execution->id)) {
                $execution->id = (string) Str::ulid();
            }
        });
    }

    public function documentJob(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentJob::class, 'job_id');
    }

    public function processor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Processor::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(array $output, int $tokensUsed = 0, int $costCredits = 0): void
    {
        $startedAt = $this->started_at ?? now();
        $durationMs = now()->diffInMilliseconds($startedAt);

        $this->update([
            'status' => 'completed',
            'output_data' => $output,
            'duration_ms' => $durationMs,
            'tokens_used' => $tokensUsed,
            'cost_credits' => $costCredits,
            'completed_at' => now(),
        ]);
    }

    public function fail(string $error): void
    {
        $startedAt = $this->started_at ?? now();
        $durationMs = now()->diffInMilliseconds($startedAt);

        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);
    }
}
