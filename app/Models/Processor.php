<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Processor Model
 * 
 * Registry of available processor implementations.
 */
class Processor extends Model
{
    use BelongsToTenant, HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'class_name',
        'category',
        'description',
        'config_schema',
        'is_system',
        'is_active',
        'version',
        'author',
        'icon',
        'documentation_url',
    ];

    protected $casts = [
        'config_schema' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
        'version' => '1.0.0',
    ];

    public function processorExecutions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProcessorExecution::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }
}
