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
 * Credential Model
 *
 * Encrypted credential storage with polymorphic scoping.
 * Can belong to: Campaign, Processor, or null (system-level)
 */
class Credential extends Model
{
    use BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'credentialable_type',
        'credentialable_id',
        'key',
        'value',
        'provider',
        'metadata',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the owning credentialable model (Campaign, Processor, or null).
     */
    public function credentialable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get/set encrypted value.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModel($query, ?Model $model = null)
    {
        if ($model === null) {
            return $query->whereNull('credentialable_type')
                ->whereNull('credentialable_id');
        }

        return $query->where('credentialable_type', get_class($model))
            ->where('credentialable_id', $model->id);
    }

    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Resolve credential with hierarchical fallback.
     *
     * Order: processor > campaign > system (null)
     */
    public static function resolve(
        string $key,
        ?Processor $processor = null,
        ?Campaign $campaign = null
    ): ?self {
        // Try processor scope first
        if ($processor) {
            $credential = self::active()
                ->notExpired()
                ->forModel($processor)
                ->forKey($key)
                ->first();

            if ($credential) {
                return $credential;
            }
        }

        // Try campaign scope
        if ($campaign) {
            $credential = self::active()
                ->notExpired()
                ->forModel($campaign)
                ->forKey($key)
                ->first();

            if ($credential) {
                return $credential;
            }
        }

        // Fall back to system scope (null credentialable)
        return self::active()
            ->notExpired()
            ->forModel(null)
            ->forKey($key)
            ->first();
    }
}
