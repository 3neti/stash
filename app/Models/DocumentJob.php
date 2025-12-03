<?php

declare(strict_types=1);

namespace App\Models;

use App\Events\DocumentProcessingCompleted;
use App\Events\DocumentProcessingFailed;
use App\States\DocumentJob\CompletedJobState;
use App\States\DocumentJob\DocumentJobState;
use App\States\DocumentJob\FailedJobState;
use App\States\DocumentJob\RunningJobState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

/**
 * DocumentJob Model
 *
 * Represents a pipeline execution instance for a document.
 */
class DocumentJob extends Model
{
    use BelongsToTenant, HasFactory, HasStates, HasUlids;

    protected $connection = 'tenant';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'campaign_id',
        'document_id',
        'pipeline_instance',
        'current_processor_index',
        'queue_name',
        'attempts',
        'max_attempts',
        'error_log',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'pipeline_instance' => 'array',
        'error_log' => 'array',
        'current_processor_index' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'state' => DocumentJobState::class,
    ];

    protected $attributes = [
        'current_processor_index' => 0,
        'attempts' => 0,
        'max_attempts' => 3,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DocumentJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function processorExecutions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProcessorExecution::class, 'job_id');
    }

    public function scopeRunning($query)
    {
        return $query->whereState('state', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->whereState('state', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->whereState('state', 'failed');
    }

    public function isRunning(): bool
    {
        return $this->state instanceof RunningJobState;
    }

    public function isCompleted(): bool
    {
        return $this->state instanceof CompletedJobState;
    }

    public function isFailed(): bool
    {
        return $this->state instanceof FailedJobState;
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function start(): void
    {
        $this->state->transitionTo('running');
        $this->update(['started_at' => now()]);
    }

    public function complete(): void
    {
        $this->state->transitionTo('completed');
        $this->update(['completed_at' => now()]);

        // Fire webhook event
        DocumentProcessingCompleted::dispatch(
            $this->campaign,
            $this->document,
            $this
        );
    }

    public function fail(string $error): void
    {
        // If already completed or failed, do not attempt to transition
        if ($this->isCompleted() || $this->isFailed()) {
            return;
        }
        
        $errorLog = $this->error_log ?? [];
        $errorLog[] = [
            'timestamp' => now()->toIso8601String(),
            'attempt' => $this->attempts,
            'error' => $error,
        ];

        $this->state->transitionTo('failed');
        $this->update([
            'failed_at' => now(),
            'error_log' => $errorLog,
        ]);

        // Fire webhook event
        DocumentProcessingFailed::dispatch(
            $this->campaign,
            $this->document,
            $this
        );
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function advanceProcessor(): void
    {
        $this->increment('current_processor_index');
    }
}
