<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Data\ContactData;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\KycTransaction;
use App\Services\Tenancy\TenancyService;
use Illuminate\Http\JsonResponse;

/**
 * API endpoint for fetching Contact data by KYC transaction ID.
 * 
 * Used by the callback page to check if KYC processing is complete
 * and retrieve the captured contact information + photos.
 */
class KycContactController extends Controller
{
    public function __invoke(string $transactionId, TenancyService $tenancyService): JsonResponse
    {
        // Find transaction in central registry
        $kycTransaction = KycTransaction::where('transaction_id', $transactionId)->first();
        
        if (!$kycTransaction) {
            return response()->json([
                'ready' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }
        
        // Initialize tenant context
        $tenancyService->initializeTenant($kycTransaction->tenant);
        
        // Find Contact by transaction ID in tenant database
        $contact = Contact::where('kyc_transaction_id', $transactionId)->first();
        
        if (!$contact) {
            return response()->json([
                'ready' => false,
                'message' => 'Still processing your verification. Please wait a moment and refresh.',
            ]);
        }
        
        // Return Contact data with media URLs
        return response()->json([
            'ready' => true,
            'contact' => ContactData::fromContact($contact),
        ]);
    }
}
