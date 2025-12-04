<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchKycDataFromHyperverge;
use App\Models\Document;
use App\Models\KycTransaction;
use App\Services\Tenancy\TenancyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handle KYC callbacks from HyperVerge.
 * 
 * After a user completes KYC verification on HyperVerge, they are redirected
 * to this controller with transaction ID and status in the query string.
 */
class KycCallbackController extends Controller
{
    /**
     * Handle the callback from HyperVerge after KYC completion.
     * 
     * URL format: /kyc/callback/{document_uuid}?transactionId=EKYC-XXX&status=auto_approved
     */
    public function __invoke(Request $request, string $documentUuid): Response
    {
        $transactionId = $request->query('transactionId');
        $status = $request->query('status', 'pending');

        Log::info('[KYC Callback] Received callback from HyperVerge', [
            'document_uuid' => $documentUuid,
            'transaction_id' => $transactionId,
            'status' => $status,
            'query_params' => $request->query(),
        ]);

        // Find KYC transaction in central registry
        $kycTransaction = KycTransaction::where('transaction_id', $transactionId)->first();

        if (!$kycTransaction) {
            Log::warning('[KYC Callback] Transaction not found in registry', [
                'transaction_id' => $transactionId,
                'document_uuid' => $documentUuid,
            ]);

            return Inertia::render('Kyc/Complete', [
                'success' => false,
                'message' => 'Transaction not found.',
                'transactionId' => $transactionId,
            ]);
        }

        // Mark callback as received
        $kycTransaction->markCallbackReceived($status);

        Log::info('[KYC Callback] Transaction found, initializing tenant', [
            'transaction_id' => $transactionId,
            'tenant_id' => $kycTransaction->tenant_id,
            'document_id' => $kycTransaction->document_id,
        ]);

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($kycTransaction->tenant);

        // Now find document in tenant database
        $document = Document::find($kycTransaction->document_id);

        if (!$document) {
            Log::error('[KYC Callback] Document not found in tenant database', [
                'document_id' => $kycTransaction->document_id,
                'tenant_id' => $kycTransaction->tenant_id,
            ]);

            return Inertia::render('Kyc/Complete', [
                'success' => false,
                'message' => 'Document not found.',
                'transactionId' => $transactionId,
            ]);
        }

        // Update document metadata with callback info
        $metadata = $document->metadata ?? [];
        $metadata['kyc_callback'] = [
            'received_at' => now()->toIso8601String(),
            'transaction_id' => $transactionId,
            'status' => $status,
            'query_params' => $request->query(),
        ];
        $document->update(['metadata' => $metadata]);

        Log::info('[KYC Callback] Document metadata updated', [
            'document_id' => $document->id,
            'transaction_id' => $transactionId,
        ]);

        // Dispatch job to fetch KYC data from HyperVerge immediately
        // (Don't wait for webhook - fetch proactively on callback)
        $this->dispatchKycDataFetch($kycTransaction, $transactionId, $status);

        // Return success page
        return Inertia::render('Kyc/Complete', [
            'success' => true,
            'message' => 'Thank you for completing KYC verification! We are processing your submission.',
            'transactionId' => $transactionId,
            'status' => $status,
            'document' => [
                'uuid' => $document->uuid,
                'filename' => $document->filename,
            ],
        ]);
    }

    /**
     * Dispatch job to fetch KYC data from HyperVerge.
     * 
     * Dispatches dedicated job that fetches results and artifacts immediately.
     */
    protected function dispatchKycDataFetch(
        KycTransaction $kycTransaction,
        string $transactionId,
        string $status
    ): void {
        try {
            // Dispatch standalone fetch job
            FetchKycDataFromHyperverge::dispatch($transactionId, $status);

            Log::info('[KYC Callback] Dispatched KYC data fetch job', [
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('[KYC Callback] Failed to dispatch KYC data fetch', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - callback page should still be shown
        }
    }
}
