<?php

declare(strict_types=1);

namespace App\Models;

use App\States\ProcessorExecution\ProcessorExecutionState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;

/**
 * ProcessorExecution Model
 *
 * Tracks individual processor invocations with metrics and optionally stores binary artifacts.
 */
class ProcessorExecution extends Model implements HasMedia
{
    use BelongsToTenant, HasFactory, HasStates, HasUlids, InteractsWithMedia;

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
    
    /**
     * Contacts associated with this execution (signers, recipients, etc.).
     */
    public function contacts(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(
            Contact::class,
            'contactable',
            'contactables'
        )->withPivot('relationship_type', 'metadata')
         ->withTimestamps();
    }
    
    /**
     * Get the primary signer contact for this execution.
     */
    public function signer(): ?Contact
    {
        return $this->contacts()
            ->wherePivot('relationship_type', 'signer')
            ->first();
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
        // If already completed or failed, do not attempt to transition
        if ($this->isCompleted() || $this->isFailed()) {
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

    /**
     * Register media collections for processor artifacts.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ocr-outputs')
            ->acceptsMimeTypes(['text/plain', 'text/html'])
            ->useDisk('tenant');

        $this->addMediaCollection('annotated-documents')
            ->acceptsMimeTypes(['application/pdf', 'image/png', 'image/jpeg'])
            ->useDisk('tenant');

        $this->addMediaCollection('thumbnails')
            ->acceptsMimeTypes(['image/png', 'image/jpeg'])
            ->useDisk('tenant');

        $this->addMediaCollection('extracted-data')
            ->acceptsMimeTypes(['application/json', 'text/plain'])
            ->useDisk('tenant');

        $this->addMediaCollection('conversions')
            ->acceptsMimeTypes(['application/pdf', 'image/png', 'image/jpeg'])
            ->useDisk('tenant');
        
        // eKYC signing collections
        $this->addMediaCollection('kyc_id_cards')
            ->useDisk('tenant');
        
        $this->addMediaCollection('kyc_selfies')
            ->useDisk('tenant');
        
        $this->addMediaCollection('signature_marks')
            ->useDisk('tenant');
        
        $this->addMediaCollection('signed_documents')
            ->useDisk('tenant');
        
        $this->addMediaCollection('blockchain_timestamps')
            ->acceptsMimeTypes(['application/octet-stream']) // .ots files
            ->useDisk('tenant');
    }

    /**
     * Register media conversions for image artifacts.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Generate thumbnail for image outputs
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->performOnCollections('annotated-documents', 'conversions')
            ->nonQueued();

        // Generate preview for larger viewing
        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->sharpen(5)
            ->performOnCollections('annotated-documents', 'conversions')
            ->nonQueued();
    }

    /**
     * Helper method to attach processor artifact with metadata.
     *
     * @param  string  $filePath  Path to the file to attach
     * @param  string  $collection  Collection name (ocr-outputs, annotated-documents, etc.)
     * @param  array  $properties  Custom properties (processor slug, execution timestamp, etc.)
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    public function attachArtifact(string $filePath, string $collection, array $properties = []): Media
    {
        return $this->addMedia($filePath)
            ->withCustomProperties(array_merge([
                'processor_slug' => $this->processor->slug ?? null,
                'execution_id' => $this->id,
                'document_job_id' => $this->job_id,
                'timestamp' => now()->toIso8601String(),
            ], $properties))
            ->toMediaCollection($collection);
    }
}
