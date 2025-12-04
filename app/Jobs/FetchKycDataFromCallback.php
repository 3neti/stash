<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ContactReady;
use App\Models\Contact;
use App\Models\KycTransaction;
use App\Models\ProcessorExecution;
use App\Services\HyperVerge\KycDataExtractor;
use App\Services\Tenancy\TenancyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Actions\Results\ExtractKYCImages;
use LBHurtado\HyperVerge\Actions\Results\FetchKYCResult;
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImages;
use LBHurtado\HyperVerge\Actions\Results\ValidateKYCResult;

/**
 * Fetch KYC data and artifacts from HyperVerge API after callback.
 * 
 * This job is dispatched when the callback redirect is received (user completes KYC in browser).
 * It fetches the full KYC result from HyperVerge, validates it, downloads images, and updates records.
 * 
 * Note: This handles GET callback redirects, not POST webhooks.
 */
class FetchKycDataFromCallback implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $transactionId,
        public string $status
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[FetchKycData] Starting KYC data fetch', [
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
        ]);

        // Find transaction in registry
        $kycTransaction = KycTransaction::where('transaction_id', $this->transactionId)->first();

        if (!$kycTransaction) {
            Log::error('[FetchKycData] Transaction not found', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        // Initialize tenant
        app(TenancyService::class)->initializeTenant($kycTransaction->tenant);

        // Load document for context (credential resolution)
        $document = \App\Models\Document::find($kycTransaction->document_id);

        try {
            // Fetch KYC result from HyperVerge
            // Call handle() directly to ensure we get the DTO object
            $result = FetchKYCResult::make()->handle($this->transactionId, $document);
            
            Log::info('[FetchKycData] KYC result fetched', [
                'transaction_id' => $this->transactionId,
                'application_status' => $result->applicationStatus ?? null,
            ]);

            // Validate result
            $validation = ValidateKYCResult::make()->handle($result);

            // Update transaction with data fetch result
            $kycTransaction->markDataFetchCompleted(
                $validation->valid ? 'auto_approved' : 'rejected'
            );

            if ($validation->valid) {
                $this->handleApproved($kycTransaction, $result);
            } else {
                $this->handleRejected($kycTransaction, $validation->reasons);
            }

        } catch (\Exception $e) {
            Log::error('[FetchKycData] Failed to fetch KYC data', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Retry
        }
    }

    /**
     * Handle approved KYC result.
     */
    protected function handleApproved(KycTransaction $kycTransaction, $result): void
    {
        Log::info('[FetchKycData] KYC approved', [
            'transaction_id' => $this->transactionId,
        ]);

        // Extract personal data from KYC result
        $personalData = KycDataExtractor::extractPersonalData($result);

        // Find ProcessorExecution if exists
        if ($kycTransaction->processor_execution_id) {
            $execution = ProcessorExecution::find($kycTransaction->processor_execution_id);

            if ($execution) {
                // Download and store images
                $imageData = ExtractKYCImages::run($this->transactionId);
                
                // Filter out metadata (country, document type) - only keep URLs
                $imageUrls = array_filter($imageData, function ($value, $key) {
                    return is_string($value) && str_starts_with($value, 'http');
                }, ARRAY_FILTER_USE_BOTH);
                
                if (!empty($imageUrls)) {
                    StoreKYCImages::run($execution, $imageUrls, $this->transactionId);
                    
                    Log::info('[FetchKycData] Images stored', [
                        'transaction_id' => $this->transactionId,
                        'execution_id' => $execution->id,
                        'image_count' => count($imageUrls),
                    ]);
                }

                // Update execution output with approval data and extracted personal data
                $execution->update([
                    'output_data' => array_merge($execution->output_data ?? [], [
                        'kyc_status' => 'approved',
                        'kyc_approved_at' => now()->toIso8601String(),
                        'kyc_data' => array_filter($personalData, fn($v) => $v !== null),
                    ]),
                ]);
            }
        }

        // Update or create Contact with personal data
        $contact = $this->updateContact($kycTransaction, $result, true, [], $personalData);
        
        // Copy KYC media from ProcessorExecution to Contact (permanent storage)
        if ($kycTransaction->processor_execution_id && $contact) {
            $execution = ProcessorExecution::find($kycTransaction->processor_execution_id);
            
            if ($execution) {
                $this->copyMediaToContact($execution, $contact);
            }
        }
        
        // Broadcast real-time event that Contact is ready
        if ($contact) {
            ContactReady::dispatch($contact, $this->transactionId);
            
            Log::info('[FetchKycData] ContactReady event dispatched', [
                'transaction_id' => $this->transactionId,
                'contact_id' => $contact->id,
            ]);
        }
        
        // Signal workflow to continue if workflow_id is present
        Log::info('[FetchKycData] Checking workflow_id for signaling', [
            'transaction_id' => $this->transactionId,
            'workflow_id' => $kycTransaction->workflow_id,
            'has_workflow_id' => !empty($kycTransaction->workflow_id),
        ]);
        
        if ($kycTransaction->workflow_id) {
            $this->signalWorkflowToContinue($kycTransaction, $contact);
        } else {
            Log::warning('[FetchKycData] Skipping workflow signal - no workflow_id', [
                'transaction_id' => $this->transactionId,
            ]);
        }
    }

    /**
     * Handle rejected KYC result.
     */
    protected function handleRejected(KycTransaction $kycTransaction, array $reasons): void
    {
        Log::warning('[FetchKycData] KYC rejected', [
            'transaction_id' => $this->transactionId,
            'reasons' => $reasons,
        ]);

        if ($kycTransaction->processor_execution_id) {
            $execution = ProcessorExecution::find($kycTransaction->processor_execution_id);

            if ($execution) {
                $execution->update([
                    'output_data' => array_merge($execution->output_data ?? [], [
                        'kyc_status' => 'rejected',
                        'kyc_rejection_reasons' => $reasons,
                        'kyc_rejected_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        }

        $this->updateContact($kycTransaction, [], false, $reasons);
    }

    /**
     * Copy KYC media from ProcessorExecution to Contact.
     */
    protected function copyMediaToContact(ProcessorExecution $execution, Contact $contact): void
    {
        $copiedCount = 0;
        
        // Copy ID card images
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
            $copiedCount++;
        }
        
        // Copy selfie images
        foreach ($execution->getMedia('kyc_selfies') as $media) {
            $media->copy($contact, 'kyc_selfies');
            $copiedCount++;
        }
        
        if ($copiedCount > 0) {
            Log::info('[FetchKycData] KYC media copied to Contact', [
                'contact_id' => $contact->id,
                'execution_id' => $execution->id,
                'media_count' => $copiedCount,
            ]);
        }
    }
    
    /**
     * Update or create Contact record.
     * Returns the Contact instance for further processing.
     */
    protected function updateContact(
        KycTransaction $kycTransaction,
        $result,
        bool $approved,
        array $reasons = [],
        array $personalData = []
    ): ?Contact {
        $metadata = $kycTransaction->metadata ?? [];
        $mobile = $metadata['contact_mobile'] ?? null;
        $email = $metadata['contact_email'] ?? null;

        // Find or create contact by transaction ID (primary key)
        // Mobile/email are optional - we store extracted KYC data regardless
        $contact = Contact::where('kyc_transaction_id', $this->transactionId)->first();

        $data = [
            'kyc_transaction_id' => $this->transactionId,
            'kyc_status' => $approved ? 'approved' : 'rejected',
            'kyc_completed_at' => now(),
        ];

        if (!$approved) {
            $data['kyc_rejection_reasons'] = $reasons;
        }

        // Merge personal data if approved and available
        if ($approved && !empty($personalData)) {
            $data = array_merge($data, array_filter($personalData, fn($v) => $v !== null));
            
            Log::info('[FetchKycData] Merging personal data into Contact', [
                'fields' => array_keys(array_filter($personalData, fn($v) => $v !== null)),
            ]);
        }

        if ($contact) {
            $contact->update($data);
            Log::info('[FetchKycData] Contact updated', ['contact_id' => $contact->id]);
        } else {
            // Create new Contact with transaction ID as unique key
            $contact = Contact::create(array_merge($data, [
                'mobile' => !empty($mobile) ? $mobile : null,
                'email' => !empty($email) ? $email : null,
                'name' => $metadata['contact_name'] ?? $personalData['name'] ?? null,
            ]));
            Log::info('[FetchKycData] Contact created from KYC data', [
                'contact_id' => $contact->id,
                'has_mobile' => !empty($mobile),
                'has_email' => !empty($email),
            ]);
        }
        
        return $contact;
    }
    
    /**
     * Signal workflow to continue after KYC callback completes.
     * 
     * Sends a signal to the workflow that was waiting for this callback,
     * allowing it to resume and continue to the next processors.
     */
    protected function signalWorkflowToContinue(
        KycTransaction $kycTransaction,
        ?Contact $contact
    ): void {
        try {
            $workflow = \Workflow\WorkflowStub::load($kycTransaction->workflow_id);
            
            if (!$workflow) {
                Log::warning('[FetchKycData] Workflow not found for signal', [
                    'workflow_id' => $kycTransaction->workflow_id,
                    'transaction_id' => $this->transactionId,
                ]);
                return;
            }
            
            $callbackData = [
                'kyc_status' => 'approved',
                'contact_id' => $contact?->id,
                'kyc_completed_at' => now()->toIso8601String(),
            ];
            
            // Call the receiveKycCallback signal method
            $workflow->receiveKycCallback($this->transactionId, $callbackData);
            
            Log::info('[FetchKycData] Workflow signaled to continue', [
                'workflow_id' => $kycTransaction->workflow_id,
                'transaction_id' => $this->transactionId,
                'contact_id' => $contact?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[FetchKycData] Failed to signal workflow', [
                'workflow_id' => $kycTransaction->workflow_id,
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - callback completion should succeed even if signal fails
        }
    }
}
