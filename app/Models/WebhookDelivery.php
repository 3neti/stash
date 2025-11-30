<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WebhookDelivery Model
 *
 * Tracks webhook delivery attempts and responses.
 */
class WebhookDelivery extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'campaign_id',
        'event_type',
        'payload',
        'response_status',
        'response_body',
        'attempted_at',
        'delivered_at',
        'failed_at',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
        'response_status' => 'integer',
    ];

    /**
     * Campaign relationship.
     */
    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Check if delivery was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Check if delivery failed.
     */
    public function hasFailed(): bool
    {
        return $this->failed_at !== null;
    }
}
