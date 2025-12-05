<?php

declare(strict_types=1);

namespace App\Models;

use App\States\Campaign\ActiveCampaignState;
use App\States\Campaign\ArchivedCampaignState;
use App\States\Campaign\CampaignState;
use App\States\Campaign\DraftCampaignState;
use App\States\Campaign\PausedCampaignState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;
use LBHurtado\ModelChannel\Traits\HasChannels;
use Spatie\ModelStates\HasStates;

/**
 * Campaign Model
 *
 * Represents a document processing workflow (stash) owned by a tenant.
 *
 * @property string $id ULID primary key
 * @property string $name Campaign name
 * @property string $slug URL-friendly identifier
 * @property string|null $description Campaign description
 * @property \App\States\Campaign\CampaignState $state draft|active|paused|archived
 * @property string $type template|custom|meta
 * @property array $pipeline_config Processor graph definition
 * @property array|null $checklist_template Checklist items
 * @property array|null $settings Queue, AI routing preferences
 * @property array|null $allowed_mime_types Accepted file MIME types
 * @property int $max_file_size_bytes Maximum file size in bytes
 * @property string|null $credentials Campaign-level credential overrides (encrypted)
 * @property int $max_concurrent_jobs Maximum concurrent jobs
 * @property int $retention_days Data retention days
 * @property \Carbon\Carbon|null $published_at Publishing timestamp
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Campaign extends Model implements AuthenticatableContract
{
    use Authorizable, BelongsToTenant, HasApiTokens, HasChannels, HasFactory, HasStates, HasUlids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'state',
        'type',
        'pipeline_config',
        'checklist_template',
        'settings',
        'notification_settings',
        'allowed_mime_types',
        'max_file_size_bytes',
        'credentials',
        'max_concurrent_jobs',
        'retention_days',
        'published_at',
    ];

    protected $casts = [
        'pipeline_config' => 'array',
        'checklist_template' => 'array',
        'settings' => 'array',
        'notification_settings' => 'array',
        'allowed_mime_types' => 'array',
        'max_file_size_bytes' => 'integer',
        'max_concurrent_jobs' => 'integer',
        'retention_days' => 'integer',
        'published_at' => 'datetime',
        'state' => CampaignState::class,
    ];

    protected $attributes = [
        'type' => 'custom',
        'max_file_size_bytes' => 10485760, // 10MB
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
        return $query->whereState('state', ActiveCampaignState::class);
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
        return $query->whereState('state', DraftCampaignState::class);
    }

    /**
     * Check if campaign is active.
     */
    public function isActive(): bool
    {
        return $this->state instanceof ActiveCampaignState;
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
        $this->state->transitionTo(ActiveCampaignState::class);
        $this->update(['published_at' => now()]);
    }

    /**
     * Pause the campaign.
     */
    public function pause(): void
    {
        $this->state->transitionTo(PausedCampaignState::class);
        $this->save();
    }

    /**
     * Archive the campaign.
     */
    public function archive(): void
    {
        $this->state->transitionTo(ArchivedCampaignState::class);
        $this->save();
    }

    /**
     * Get processor count from pipeline config.
     */
    public function getProcessorCountAttribute(): int
    {
        return count($this->pipeline_config['processors'] ?? []);
    }

    /**
     * Check if campaign accepts a specific MIME type.
     */
    public function acceptsMimeType(string $mimeType): bool
    {
        $allowed = $this->allowed_mime_types ?? [];

        // If no restrictions configured, accept common types
        if (empty($allowed)) {
            return in_array($mimeType, [
                'application/pdf',
                'image/png',
                'image/jpeg',
                'image/jpg',
                'image/tiff',
            ]);
        }

        return in_array($mimeType, $allowed);
    }

    /**
     * Get allowed file extensions based on MIME types.
     */
    public function getAcceptedExtensions(): array
    {
        $mimeTypes = $this->allowed_mime_types ?? [];

        if (empty($mimeTypes)) {
            return ['pdf', 'png', 'jpg', 'jpeg', 'tiff'];
        }

        return $this->mimeTypesToExtensions($mimeTypes);
    }

    /**
     * Get allowed MIME types (with defaults if not configured).
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowed_mime_types ?? [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/tiff',
        ];
    }

    /**
     * Get maximum file size in bytes.
     */
    public function getMaxFileSizeBytes(): int
    {
        return $this->max_file_size_bytes ?? 10485760; // 10MB default
    }

    /**
     * Get maximum file size in megabytes.
     */
    public function getMaxFileSizeMB(): float
    {
        return round($this->getMaxFileSizeBytes() / 1048576, 2);
    }

    /**
     * Convert MIME types to file extensions.
     */
    private function mimeTypesToExtensions(array $mimeTypes): array
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/tiff' => 'tiff',
            'image/tif' => 'tiff',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'text/markdown' => 'md',
            'text/plain' => 'txt',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        $extensions = [];
        foreach ($mimeTypes as $mimeType) {
            if (isset($map[$mimeType])) {
                $extensions[] = $map[$mimeType];
            }
        }

        return array_unique($extensions);
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        //
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /**
     * Get the password column name for the user.
     */
    public function getAuthPasswordName(): ?string
    {
        return null;
    }
}
