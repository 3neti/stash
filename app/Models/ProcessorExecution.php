<?php

declare(strict_types=1);

namespace App\Models;

use App\States\ProcessorExecution\ProcessorExecutionState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\HasStates;

/**
 * ProcessorExecution Model
 *
 * Tracks individual processor invocations with metrics.
 */
class ProcessorExecution extends Model
{
    use BelongsToTenant, HasFactory, HasStates, HasUlids;

    protected $connection = 'tenant';

    protected $fillable = [
        'job_id',
        'processor_id',
        'input_data',
        'output_data',
        'config',
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
        'state' => ProcessorExecutionState::class,
    ];

    protected $attributes = [
        'tokens_used' => 0,
        'cost_credits' => 0,
    ];

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
        return $query->whereState('state', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->whereState('state', 'failed');
    }

    public function isCompleted(): bool
    {
        return $this->state instanceof \App\States\ProcessorExecution\CompletedExecutionState;
    }

    public function isFailed(): bool
    {
        return $this->state instanceof \App\States\ProcessorExecution\FailedExecutionState;
    }

    public function start(): void
    {
        $this->state->transitionTo('running');
        $this->update(['started_at' => now()]);
    }

    public function complete(array $output, int $tokensUsed = 0, int $costCredits = 0): void
    {
        $startedAt = $this->started_at ?? $this->created_at ?? now();
        $durationMs = (int) abs(now()->diffInMilliseconds($startedAt));

        DB::transaction(function () use ($output, $durationMs, $tokensUsed, $costCredits) {
            $this->update([
                'output_data' => $output,
                'duration_ms' => $durationMs,
                'tokens_used' => $tokensUsed,
                'cost_credits' => $costCredits,
                'completed_at' => now(),
            ]);

            $this->state->transitionTo('completed');
        });
    }

    public function fail(string $error): void
    {
        // If already completed, do not attempt to transition to failed
        if ($this->isCompleted()) {
            return;
        }

        $startedAt = $this->started_at ?? $this->created_at ?? now();
        $durationMs = (int) abs(now()->diffInMilliseconds($startedAt));

        DB::transaction(function () use ($error, $durationMs) {
            $this->update([
                'error_message' => $error,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $this->state->transitionTo('failed');
        });
    }
}
