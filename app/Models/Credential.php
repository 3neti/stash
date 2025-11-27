<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Credential Model
 * 
 * Encrypted credential storage with hierarchical scoping.
 * Scopes: system > subscriber > campaign > processor
 */
class Credential extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'scope_type',
        'scope_id',
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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Credential $credential) {
            if (empty($credential->id)) {
                $credential->id = (string) Str::ulid();
            }
        });
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

    public function scopeForScope($query, string $scopeType, ?string $scopeId = null)
    {
        return $query->where('scope_type', $scopeType)
                     ->where('scope_id', $scopeId);
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
        return $this->is_active && !$this->isExpired();
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Resolve credential with hierarchical fallback.
     * 
     * Order: processor > campaign > subscriber > system
     */
    public static function resolve(
        string $key,
        ?string $processorId = null,
        ?string $campaignId = null,
        ?string $subscriberId = null
    ): ?self {
        // Try processor scope first
        if ($processorId) {
            $credential = self::active()
                ->notExpired()
                ->forScope('processor', $processorId)
                ->forKey($key)
                ->first();

            if ($credential) {
                return $credential;
            }
        }

        // Try campaign scope
        if ($campaignId) {
            $credential = self::active()
                ->notExpired()
                ->forScope('campaign', $campaignId)
                ->forKey($key)
                ->first();

            if ($credential) {
                return $credential;
            }
        }

        // Try subscriber scope
        if ($subscriberId) {
            $credential = self::active()
                ->notExpired()
                ->forScope('subscriber', $subscriberId)
                ->forKey($key)
                ->first();

            if ($credential) {
                return $credential;
            }
        }

        // Fall back to system scope
        return self::active()
            ->notExpired()
            ->forScope('system', null)
            ->forKey($key)
            ->first();
    }
}
