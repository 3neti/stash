<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Contact;
use Spatie\LaravelData\Data;

/**
 * Contact Data Transfer Object
 * 
 * Represents Contact information with KYC data and media URLs.
 * Used for API responses on the KYC callback page.
 */
class ContactData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $birth_date,
        public readonly ?string $address,
        public readonly ?string $gender,
        public readonly string $kyc_status,
        public readonly ?string $kyc_completed_at,
        /** @var array<int, string> Temporary signed URLs for ID card images */
        public readonly array $id_card_urls,
        public readonly ?string $selfie_url,
    ) {}
    
    /**
     * Create ContactData from Contact model.
     * 
     * Generates temporary signed URLs for media (expire in 1 hour).
     */
    public static function fromContact(Contact $contact): self
    {
        return new self(
            id: $contact->id,
            name: $contact->name,
            birth_date: $contact->birth_date,
            address: $contact->address,
            gender: $contact->gender,
            kyc_status: $contact->kyc_status ?? 'pending',
            kyc_completed_at: $contact->kyc_completed_at?->toIso8601String(),
            id_card_urls: $contact->getMedia('kyc_id_cards')
                ->map(fn($media) => $media->getTemporaryUrl(now()->addHour()))
                ->toArray(),
            selfie_url: $contact->getFirstMedia('kyc_selfies')
                ?->getTemporaryUrl(now()->addHour()),
        );
    }
}
