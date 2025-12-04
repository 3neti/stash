<?php

namespace LBHurtado\Contact\Models;

use LBHurtado\Contact\Traits\{HasAdditionalAttributes, HasMeta};
use LBHurtado\Contact\Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use LBHurtado\Contact\Traits\HasBankAccount;
use LBHurtado\Contact\Contracts\Bankable;
use LBHurtado\Contact\Traits\HasMobile;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Class Contact.
 *
 * Generic contact model for multi-tenant applications.
 *
 * @property int         $id
 * @property string      $mobile
 * @property string      $country
 * @property string      $bank_account
 * @property string      $bank_code
 * @property string      $account_number
 * @property string      $name
 * @property string      $email
 *
 * @method int getKey()
 */
class Contact extends Model implements Bankable, HasMedia
{
    use HasAdditionalAttributes;
    use HasBankAccount;
    use HasFactory;
    use HasMobile;
    use HasMeta;
    use InteractsWithMedia;

    protected $fillable = [
        'mobile',
        'country',
        'bank_account',
        'name',
        'email',
    ];

    protected $appends = [
        'name'
    ];

    public static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->country = $contact->country
                ?: config('contact.default.country');
            // ensure there's always a bank_account like "BANK_CODE:ACCOUNT_NUMBER"
            // Only generate if bank_account not set AND mobile exists
            if (empty($contact->bank_account) && !empty($contact->mobile)) {
                $defaultCode = config('contact.default.bank_code');
                $contact->bank_account = "{$defaultCode}:{$contact->mobile}";
            }
        });
    }

    public static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    /**
     * Register media collections.
     * Override in app-specific Contact model for custom collections.
     */
    public function registerMediaCollections(): void
    {
        // Empty by default - override in app/Models/Contact.php
    }

    public function getBankCodeAttribute(): ?string
    {
        if (!$this->bank_account) {
            return null;
        }
        return $this->getBankCode();
    }

    public function getAccountNumberAttribute(): ?string
    {
        if (!$this->bank_account) {
            return null;
        }
        return $this->getAccountNumber();
    }
    
}
