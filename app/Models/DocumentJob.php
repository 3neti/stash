<?php

declare(strict_types=1);

namespace App\Models;

use App\States\DocumentJob\DocumentJobState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * DocumentJob Model
 * 
 * Represents a pipeline execution instance for a document.
 */
class DocumentJob extends Model
{
    use BelongsToTenant, HasFactory, HasStates;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'campaign_id',
        'document_id',
        'pipeline_instance',
        'current_processor_index',
        'status',
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
        'status' => DocumentJobState::class,
    ];

    protected $attributes = [
        'status' => 'pending',
        'current_processor_index' => 0,
        'attempts' => 0,
        'max_attempts' => 3,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DocumentJob $job) {
            if (empty($job->id)) {
                $job->id = (string) Str::ulid();
            }
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
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
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function fail(string $error): void
    {
        $errorLog = $this->error_log ?? [];
        $errorLog[] = [
            'timestamp' => now()->toIso8601String(),
            'attempt' => $this->attempts,
            'error' => $error,
        ];

        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_log' => $errorLog,
        ]);
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
