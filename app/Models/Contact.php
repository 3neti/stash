<?php

namespace App\Models;

use App\Models\Concerns\HasKycAccess;
use App\Tenancy\Traits\BelongsToTenant;
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
    
    protected $connection = 'tenant';
    
    protected $fillable = [
        'mobile',
        'country',
        'bank_account',
        'name',
        'email',
        'kyc_transaction_id',
        'kyc_status',
        'kyc_onboarding_url',
        'kyc_submitted_at',
        'kyc_completed_at',
        'kyc_rejection_reasons',
    ];
    
    protected $casts = [
        'kyc_submitted_at' => 'datetime',
        'kyc_completed_at' => 'datetime',
        'kyc_rejection_reasons' => 'array',
    ];
    
    /**
     * Register media collections for Contact.
     */
    public function registerMediaCollections(): void
    {
        // Only generic profile photos (not eKYC media)
        $this->addMediaCollection('profile_photos')
            ->singleFile()
            ->useDisk('tenant');
    }
}
