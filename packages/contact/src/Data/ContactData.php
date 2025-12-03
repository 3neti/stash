<?php

namespace LBHurtado\Contact\Data;

use LBHurtado\Contact\Models\Contact as ContactModel;
use Spatie\LaravelData\Data;

class ContactData extends Data
{
    public function __construct(
        public string $mobile,
        public string $country,
        public ?string $bank_account = null,
        public ?string $bank_code = null,
        public ?string $account_number = null,
        public ?string $name = null,
    ) {}

    public static function fromModel(ContactModel $contact): static
    {
        return new static(
            mobile: $contact->mobile,
            country: $contact->country,
            bank_account: $contact->bank_account,
            bank_code: $contact->bank_account ? $contact->bank_code : null,
            account_number: $contact->bank_account ? $contact->account_number : null,
            name: $contact->name
        );
    }
}
