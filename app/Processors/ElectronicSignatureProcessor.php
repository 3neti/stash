<?php

declare(strict_types=1);

namespace App\Processors;

use App\Data\Pipeline\ProcessorConfigData;
use App\Models\Contact;
use App\Models\Document;
use App\Models\ProcessorExecution;
use LBHurtado\HyperVerge\Actions\Document\MarkDocumentWithKYC;

/**
 * Electronic Signature Processor
 * 
 * Signs documents with eKYC verification data using HyperVerge.
 * Requires prior eKYC verification (EKycVerificationProcessor).
 * 
 * Features:
 * - PKCS#7 digital signatures with tamper detection
 * - QR code watermark for instant verification
 * - ID image stamp with metadata overlay
 * - Blockchain timestamping via OpenTimestamps
 * - Certificate generation
 */
class ElectronicSignatureProcessor extends AbstractProcessor
{
    protected string $name = 'Electronic Signature';
    protected string $category = 'signing';

    protected function process(Document $document, ProcessorConfigData $config): array
    {
        // 1. Validate dependencies - ensure eKYC verification ran first
        // This is automatically enforced by the workflow system using Processor->dependencies
        // But we add an explicit check here for better error messages
        
        // 2. Get transaction_id from config or context
        $transactionId = $config->config['transaction_id'] ?? null;
        
        if (!$transactionId) {
            throw new \RuntimeException('transaction_id is required for electronic signature');
        }
        
        // 2. Verify transaction_id has approved KYC
        $contact = Contact::where('kyc_transaction_id', $transactionId)->first();
        
        if (!$contact || !$contact->isKycApproved()) {
            throw new \RuntimeException(
                "KYC verification not approved for transaction: {$transactionId}. " .
                "Run eKYC Verification processor first."
            );
        }
        
        // 3. Get document file path from Storage
        if (!$document->fileExists()) {
            throw new \RuntimeException("Document file not found: {$document->uuid}");
        }
        
        // Get absolute path for local processing
        $documentPath = \Illuminate\Support\Facades\Storage::disk($document->storage_disk)
            ->path($document->storage_path);
        
        // 4. Prepare metadata from contact
        $metadata = array_filter([
            'name' => $contact->name,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
        ]);
        
        // Merge with additional metadata from config
        $metadata = array_merge(
            $metadata,
            $config->config['metadata'] ?? []
        );
        
        // 5. Get configuration options
        $tile = $config->config['tile'] ?? 1; // Signature position (1-9)
        $logoPath = $config->config['logo_path'] ?? null;
        
        // 6. Sign document using HyperVerge package
        $result = MarkDocumentWithKYC::run(
            model: $document, // Use Document model directly
            transactionId: $transactionId,
            additionalMetadata: $metadata,
            tile: $tile,
            logoPath: $logoPath
        );
        
        // 7. Store signed document and stamp in media collections
        $signedDocument = $result['signed_document']; // Media object
        $stamp = $result['stamp']; // Media object
        
        // 8. Generate verification URL (placeholder for now)
        // TODO: Create documents.verify route for QR code verification
        $verificationUrl = config('app.url') . '/documents/verify/' . $document->uuid . '/' . $transactionId;
        
        // 9. Get signer info from Contact
        $signerInfo = [
            'contact_id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
            'kyc_status' => $contact->kyc_status,
            'kyc_completed_at' => $contact->kyc_completed_at?->toIso8601String(),
        ];
        
        // 10. Return output
        return [
            'signed_document' => [
                'media_id' => $signedDocument->id,
                'file_name' => $signedDocument->file_name,
                'size' => $signedDocument->size,
                'mime_type' => $signedDocument->mime_type,
                'url' => $signedDocument->getUrl(),
            ],
            'stamp' => [
                'media_id' => $stamp->id,
                'file_name' => $stamp->file_name,
                'url' => $stamp->getUrl(),
            ],
            'transaction_id' => $transactionId,
            'verification_url' => $verificationUrl,
            'signer_info' => $signerInfo,
            'signature_timestamp' => now()->toIso8601String(),
            'tile_position' => $tile,
            'metadata' => $metadata,
        ];
    }
    
    public function canProcess(Document $document): bool
    {
        // Only process PDF documents
        return in_array($document->mime_type, [
            'application/pdf',
        ]);
    }
    
    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signed_document' => [
                    'type' => 'object',
                    'properties' => [
                        'media_id' => ['type' => 'integer'],
                        'file_name' => ['type' => 'string'],
                        'size' => ['type' => 'integer'],
                        'mime_type' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                    ],
                    'required' => ['media_id', 'file_name', 'url'],
                ],
                'stamp' => [
                    'type' => 'object',
                    'properties' => [
                        'media_id' => ['type' => 'integer'],
                        'file_name' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                    ],
                    'required' => ['media_id', 'url'],
                ],
                'transaction_id' => ['type' => 'string'],
                'verification_url' => ['type' => 'string'],
                'signer_info' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string'],
                        'name' => ['type' => ['string', 'null']],
                        'email' => ['type' => ['string', 'null']],
                        'mobile' => ['type' => ['string', 'null']],
                        'kyc_status' => ['type' => 'string'],
                        'kyc_completed_at' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['contact_id', 'kyc_status'],
                ],
                'signature_timestamp' => ['type' => 'string'],
                'tile_position' => ['type' => 'integer'],
                'metadata' => ['type' => 'object'],
            ],
            'required' => [
                'signed_document',
                'stamp',
                'transaction_id',
                'verification_url',
                'signer_info',
                'signature_timestamp',
            ],
        ];
    }
}
