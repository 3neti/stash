<?php

declare(strict_types=1);

namespace App\Models;

use App\States\Document\DocumentState;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * Document Model
 * 
 * Represents an uploaded file and its processing state.
 */
class Document extends Model
{
    use BelongsToTenant, HasFactory, HasStates, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'campaign_id',
        'user_id',
        'original_filename',
        'mime_type',
        'size_bytes',
        'storage_path',
        'storage_disk',
        'hash',
        'status',
        'metadata',
        'processing_history',
        'error_message',
        'retry_count',
        'processed_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processing_history' => 'array',
        'size_bytes' => 'integer',
        'retry_count' => 'integer',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'status' => DocumentState::class,
    ];

    protected $attributes = [
        'status' => 'pending',
        'retry_count' => 0,
        'storage_disk' => 's3',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Document $document) {
            if (empty($document->id)) {
                $document->id = (string) Str::ulid();
            }
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DocumentJob::class);
    }

    public function usageEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
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

    public function isProcessing(): bool
    {
        return in_array($this->status, ['queued', 'processing']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public function incrementRetries(): void
    {
        $this->increment('retry_count');
    }

    public function getUrl(): string
    {
        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }

    public function getContents(): string
    {
        return Storage::disk($this->storage_disk)->get($this->storage_path);
    }

    public function deleteFile(): bool
    {
        return Storage::disk($this->storage_disk)->delete($this->storage_path);
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->storage_path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size_bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function addProcessingHistory(string $stage, array $data): void
    {
        $history = $this->processing_history ?? [];
        $history[] = [
            'stage' => $stage,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];
        $this->update(['processing_history' => $history]);
    }
}
