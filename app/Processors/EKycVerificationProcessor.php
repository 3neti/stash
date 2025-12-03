<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Contact;
use App\Models\Document;
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;

/**
 * EKyc Verification Processor
 * 
 * Generates HyperVerge eKYC onboarding links for document signers.
 * Creates Contact records and initiates KYC verification workflow.
 */
class EKycVerificationProcessor extends AbstractProcessor
{
    protected string $name = 'eKYC Verification';
    protected string $category = 'verification';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // 1. Extract contact data from config/context
        $contactData = $config->config['contact'] ?? [];
        
        // 2. Find or create contact
        $contact = null;
        if (!empty($contactData['mobile'])) {
            // Format mobile number for search (HasMobile trait will format during create)
            $country = $contactData['country'] ?? $config->config['country'] ?? 'PH';
            $formattedMobile = phone($contactData['mobile'], $country)
                ->formatForMobileDialingInCountry($country);
            
            $contact = Contact::where('mobile', $formattedMobile)->first();
            
            if (!$contact) {
                $contact = Contact::create(array_merge(
                    $contactData,
                    ['country' => $country]
                ));
            }
        }
        
        // 3. Check if contact already has approved KYC
        if ($contact && $contact->isKycApproved()) {
            return [
                'kyc_status' => 'already_approved',
                'contact_id' => $contact->id,
                'transaction_id' => $contact->kyc_transaction_id,
                'skip_verification' => true,
            ];
        }
        
        // 4. Generate transaction ID
        $transactionId = $this->generateTransactionId($document, $config);
        
        // 5. Generate HyperVerge onboarding link
        $redirectUrl = $config->config['redirect_url'] 
            ?? config('app.url') . '/kyc/callback/' . $document->uuid;
        
        $link = GenerateOnboardingLink::get(
            transactionId: $transactionId,
            workflowId: $config->config['workflow_id'] ?? 'workflow_2nQDNT',
            redirectUrl: $redirectUrl
        );
        
        // 6. Store in contact if exists
        if ($contact) {
            $contact->update([
                'kyc_transaction_id' => $transactionId,
                'kyc_onboarding_url' => $link,
                'kyc_status' => 'pending',
                'kyc_submitted_at' => now(),
            ]);
        }
        
        // 7. Return output (stored in ProcessorExecution.output_data)
        return [
            'transaction_id' => $transactionId,
            'kyc_link' => $link,
            'kyc_status' => 'pending',
            'contact_id' => $contact?->id,
            'contact_mobile' => $contactData['mobile'] ?? null,
            'contact_email' => $contactData['email'] ?? null,
            'contact_name' => $contactData['name'] ?? null,
            'workflow_id' => $config->config['workflow_id'] ?? 'workflow_2nQDNT',
            'awaiting_webhook' => true,
            'redirect_url' => $redirectUrl,
        ];
    }
    
    /**
     * Generate unique transaction ID for this KYC verification.
     */
    private function generateTransactionId(Document $document, ProcessorConfigData $config): string
    {
        $prefix = $config->config['transaction_prefix'] ?? 'ekyc';
        
        return implode('_', [
            $prefix,
            $document->campaign_id,
            $document->id,
            now()->timestamp,
            str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT)
        ]);
    }
    
    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transaction_id' => ['type' => 'string'],
                'kyc_link' => ['type' => 'string'],
                'kyc_status' => ['type' => 'string', 'enum' => ['pending', 'already_approved']],
                'contact_id' => ['type' => ['integer', 'null']],
                'contact_mobile' => ['type' => ['string', 'null']],
                'contact_email' => ['type' => ['string', 'null']],
                'contact_name' => ['type' => ['string', 'null']],
                'workflow_id' => ['type' => 'string'],
                'awaiting_webhook' => ['type' => 'boolean'],
                'redirect_url' => ['type' => 'string'],
            ],
            'required' => ['transaction_id', 'kyc_link', 'kyc_status'],
        ];
    }
}
