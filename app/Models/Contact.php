<?php

namespace App\Models;

use App\Models\Concerns\HasKycAccess;
use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use LBHurtado\Contact\Models\Contact as BaseContact;

/**
 * Class Contact
 * 
 * Extends the base Contact package with Stash-specific eKYC functionality.
 */
class Contact extends BaseContact
{
    use BelongsToTenant;
    use HasKycAccess;
    use HasUlids;
    
    protected $connection = 'tenant';
    
    /**
     * Merge parent fillable with KYC operational fields.
     * Note: kyc_onboarding_url and kyc_rejection_reasons are stored in meta (via HasAdditionalAttributes)
     */
    public function __construct(array $attributes = [])
    {
        // Merge parent fillable with KYC operational database columns only
        $this->fillable = array_merge(parent::$fillable ?? [], [
            'kyc_transaction_id',  // For lookups/querying
            'kyc_status',          // For filtering
            'kyc_submitted_at',    // Timestamps
            'kyc_completed_at',
        ]);
        
        parent::__construct($attributes);
    }
    
    protected $casts = [
        'kyc_submitted_at' => 'datetime',
        'kyc_completed_at' => 'datetime',
    ];
    
    /**
     * Register media collections for Contact.
     */
    public function registerMediaCollections(): void
    {
        // Profile photos
        $this->addMediaCollection('profile_photos')
            ->singleFile()
            ->useDisk('tenant');
        
        // KYC ID card images (full + cropped)
        $this->addMediaCollection('kyc_id_cards')
            ->useDisk('tenant');
        
        // KYC selfie image
        $this->addMediaCollection('kyc_selfies')
            ->singleFile()
            ->useDisk('tenant');
    }
}
