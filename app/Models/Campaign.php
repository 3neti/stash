<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Campaign Model
 * 
 * Represents a document processing workflow (stash) owned by a tenant.
 * 
 * @property string $id ULID primary key
 * @property string $name Campaign name
 * @property string $slug URL-friendly identifier
 * @property string|null $description Campaign description
 * @property string $status draft|active|paused|archived
 * @property string $type template|custom|meta
 * @property array $pipeline_config Processor graph definition
 * @property array|null $checklist_template Checklist items
 * @property array|null $settings Queue, AI routing, file rules
 * @property string|null $credentials Campaign-level credential overrides (encrypted)
 * @property int $max_concurrent_jobs Maximum concurrent jobs
 * @property int $retention_days Data retention days
 * @property \Carbon\Carbon|null $published_at Publishing timestamp
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Campaign extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'type',
        'pipeline_config',
        'checklist_template',
        'settings',
        'credentials',
        'max_concurrent_jobs',
        'retention_days',
        'published_at',
    ];

    protected $casts = [
        'pipeline_config' => 'array',
        'checklist_template' => 'array',
        'settings' => 'array',
        'max_concurrent_jobs' => 'integer',
        'retention_days' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'type' => 'custom',
        'max_concurrent_jobs' => 10,
        'retention_days' => 90,
    ];

    /**
     * Get/set encrypted credentials.
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Get documents in this campaign.
     */
    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get document jobs in this campaign.
     */
    public function documentJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentJob::class);
    }

    /**
     * Get usage events for this campaign.
     */
    public function usageEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    /**
     * Scope: Active campaigns only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Published campaigns only.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope: Draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Check if campaign is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if campaign is published.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Publish the campaign.
     */
    public function publish(): void
    {
        $this->update([
            'published_at' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * Pause the campaign.
     */
    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    /**
     * Archive the campaign.
     */
    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }

    /**
     * Get processor count from pipeline config.
     */
    public function getProcessorCountAttribute(): int
    {
        return count($this->pipeline_config['processors'] ?? []);
    }
}
