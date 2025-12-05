<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\KycTransaction;
use App\Services\Tenancy\TenancyService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Document Verification Controller
 * 
 * Public endpoint for verifying digitally signed documents.
 * Anyone with the verification URL can confirm document authenticity.
 */
class DocumentVerificationController extends Controller
{
    /**
     * Show document verification page.
     * 
     * @param string $documentUuid Document UUID
     * @param string $transactionId KYC transaction ID
     */
    public function __invoke(string $documentUuid, string $transactionId): Response
    {
        // Find KYC transaction in central registry
        $kycTransaction = KycTransaction::where('transaction_id', $transactionId)->first();

        if (!$kycTransaction) {
            return Inertia::render('documents/Verify', [
                'verified' => false,
                'error' => 'Verification failed: Transaction not found.',
            ]);
        }

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($kycTransaction->tenant);

        // Find document by UUID
        $document = Document::where('uuid', $documentUuid)->first();

        if (!$document) {
            return Inertia::render('documents/Verify', [
                'verified' => false,
                'error' => 'Verification failed: Document not found.',
            ]);
        }

        // Get document job
        $documentJob = $document->documentJob;

        if (!$documentJob) {
            return Inertia::render('documents/Verify', [
                'verified' => false,
                'error' => 'Verification failed: Document processing information not found.',
            ]);
        }

        // Find electronic signature processor execution
        $signatureExecution = $documentJob->processorExecutions()
            ->whereHas('processor', fn($q) => $q->where('slug', 'electronic-signature'))
            ->where('state', 'completed')
            ->first();

        if (!$signatureExecution) {
            return Inertia::render('documents/Verify', [
                'verified' => false,
                'error' => 'Verification failed: Digital signature not found.',
            ]);
        }

        // Get signed document from media
        $signedDoc = $signatureExecution->getFirstMedia('signed_documents');
        $signatureMark = $signatureExecution->getFirstMedia('signature_marks');

        if (!$signedDoc) {
            return Inertia::render('documents/Verify', [
                'verified' => false,
                'error' => 'Verification failed: Signed document file not found.',
            ]);
        }

        // Find eKYC verification processor execution
        $kycExecution = $documentJob->processorExecutions()
            ->whereHas('processor', fn($q) => $q->where('slug', 'ekyc-verification'))
            ->first();

        // Prepare verification data
        $verificationData = [
            'verified' => true,
            'document' => [
                'uuid' => $document->uuid,
                'filename' => $document->filename,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'size_kb' => round($document->size_bytes / 1024, 2),
            ],
            'signature' => [
                'transaction_id' => $transactionId,
                'signed_at' => $signedDoc->getCustomProperty('signed_at'),
                'qr_watermarked' => $signedDoc->getCustomProperty('qr_watermarked', false),
                'verification_url' => $signedDoc->getCustomProperty('verification_url'),
                'processor' => 'Electronic Signature Processor',
                'status' => 'Verified',
            ],
            'signed_document' => [
                'filename' => $signedDoc->file_name,
                'size_kb' => round($signedDoc->size / 1024, 2),
                'download_url' => route('documents.signed.download', [
                    'tenant' => $kycTransaction->tenant_id,
                    'execution' => $signatureExecution->id,
                ]),
            ],
            'kyc' => $kycExecution ? [
                'transaction_id' => $transactionId,
                'status' => $kycExecution->state::class,
                'completed_at' => $kycExecution->completed_at?->toIso8601String(),
            ] : null,
        ];

        if ($signatureMark) {
            $verificationData['signature_mark_url'] = $signatureMark->getUrl();
        }

        return Inertia::render('documents/Verify', $verificationData);
    }
}
