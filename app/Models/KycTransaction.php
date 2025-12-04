<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KYC Transaction Registry (Central Database)
 * 
 * Maps HyperVerge transaction IDs to tenant/document context.
 * Used by public webhook/callback endpoints to locate the correct tenant and resources.
 * 
 * Possible statuses from HyperVerge:
 * - auto_approved: Automatically approved by HyperVerge
 * - approved: Manually approved
 * - needs_review: Requires manual review
 * - auto_declined: Automatically declined
 * - rejected: Manually rejected
 * - user_cancelled: User cancelled the verification
 * - error: Error during verification
 */
class KycTransaction extends Model
{
    protected $connection = 'central';
    
    protected $fillable = [
        'transaction_id',
        'tenant_id',
        'document_id',
        'processor_execution_id',
        'status',
        'metadata',
        'callback_received_at',
        'webhook_received_at',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'callback_received_at' => 'datetime',
        'webhook_received_at' => 'datetime',
    ];
    
    /**
     * Get the tenant that owns this transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    /**
     * Check if status indicates approval.
     */
    public function isApproved(): bool
    {
        return in_array($this->status, ['auto_approved', 'approved', 'needs_review']);
    }
    
    /**
     * Check if status indicates rejection.
     */
    public function isRejected(): bool
    {
        return in_array($this->status, ['auto_declined', 'rejected', 'user_cancelled', 'error']);
    }
    
    /**
     * Mark callback as received.
     */
    public function markCallbackReceived(string $status): void
    {
        $this->update([
            'status' => $status,
            'callback_received_at' => now(),
        ]);
    }
    
    /**
     * Mark webhook as received.
     */
    public function markWebhookReceived(string $status): void
    {
        $this->update([
            'status' => $status,
            'webhook_received_at' => now(),
        ]);
    }
}
