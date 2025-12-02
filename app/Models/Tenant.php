<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $connection = 'central';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'status',
        'tier',
        'settings',
        'credentials',
        'credit_balance',
        'trial_ends_at',
        'suspended_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'credit_balance' => 'integer',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * Get the encrypted credentials attribute.
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get tenant users.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Get tenant domains.
     */
    public function domains(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Domain::class);
    }
}
