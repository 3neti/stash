<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * UsageEvent Model
 * 
 * Append-only metering and billing events.
 */
class UsageEvent extends Model
{
    use BelongsToTenant, HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id',
        'document_id',
        'job_id',
        'event_type',
        'units',
        'cost_credits',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'units' => 'integer',
        'cost_credits' => 'integer',
        'recorded_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (UsageEvent $event) {
            if (empty($event->id)) {
                $event->id = (string) Str::ulid();
            }
            if (empty($event->recorded_at)) {
                $event->recorded_at = now();
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

    public function documentJob(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentJob::class, 'job_id');
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Get total credits used in a period.
     */
    public static function totalCredits($startDate = null, $endDate = null): int
    {
        $query = self::query();

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query->sum('cost_credits');
    }

    /**
     * Get usage breakdown by event type.
     */
    public static function breakdownByType($startDate = null, $endDate = null): array
    {
        $query = self::query();

        if ($startDate && $endDate) {
            $query->inPeriod($startDate, $endDate);
        }

        return $query
            ->selectRaw('event_type, SUM(units) as total_units, SUM(cost_credits) as total_credits')
            ->groupBy('event_type')
            ->get()
            ->keyBy('event_type')
            ->toArray();
    }
}
