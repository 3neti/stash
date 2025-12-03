<?php

namespace App\Models\Concerns;

use App\Models\ProcessorExecution;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait HasKycAccess
 * 
 * Provides eKYC-specific methods for Contact model.
 * This trait connects Contacts to ProcessorExecutions for signing history.
 */
trait HasKycAccess
{
    /**
     * ProcessorExecutions related to this contact (eKYC signing events).
     */
    public function processorExecutions(): MorphToMany
    {
        return $this->morphedByMany(
            ProcessorExecution::class,
            'contactable',
            'contactables'
        )->withPivot('relationship_type', 'metadata')
         ->withTimestamps();
    }
    
    /**
     * Get signing history for this contact.
     */
    public function signingHistory()
    {
        return $this->processorExecutions()
            ->whereHas('processor', fn($q) => $q->where('slug', 'ekyc-verification'))
            ->with('documentJob.document')
            ->orderByDesc('created_at');
    }
    
    /**
     * Get the most recent KYC verification execution.
     */
    public function latestKycExecution(): ?ProcessorExecution
    {
        return $this->processorExecutions()
            ->whereHas('processor', fn($q) => $q->where('slug', 'ekyc-verification'))
            ->latest()
            ->first();
    }
    
    /**
     * Get KYC ID card images (from latest execution).
     */
    public function getKycIdCards()
    {
        return $this->latestKycExecution()?->getMedia('kyc_id_cards') ?? collect();
    }
    
    /**
     * Get KYC selfie (from latest execution).
     */
    public function getKycSelfie()
    {
        return $this->latestKycExecution()?->getFirstMedia('kyc_selfies');
    }
    
    /**
     * Check if contact has approved KYC.
     */
    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }
    
    /**
     * Check if contact needs KYC verification.
     */
    public function needsKyc(): bool
    {
        return !$this->isKycApproved();
    }
}
