<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ProcessorExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Actions\Results\StoreKYCImages;
use LBHurtado\HyperVerge\Data\Responses\KYCResultData;
use LBHurtado\HyperVerge\Webhooks\ProcessHypervergeWebhookJob as BaseJob;

/**
 * Custom HyperVerge webhook processor for Stash.
 * 
 * Handles KYC completion webhooks by:
 * - Finding ProcessorExecution by transaction_id
 * - Updating execution output_data with KYC results
 * - Creating/linking Contact records
 * - Storing KYC images in media collections
 * - Resuming document processing workflow
 */
class ProcessHypervergeWebhook extends BaseJob
{
    /**
     * Find the model associated with this transaction.
     * 
     * Searches ProcessorExecution records for matching transaction_id
     * in output_data JSON column.
     *
     * @param string $transactionId HyperVerge transaction ID
     * @return Model|null ProcessorExecution or null if not found
     */
    protected function findModelForTransaction(string $transactionId): ?Model
    {
        // Search for ProcessorExecution with this transaction_id in output_data
        $execution = ProcessorExecution::query()
            ->where('output_data->transaction_id', $transactionId)
            ->whereHas('processor', fn($q) => $q->where('slug', 'ekyc-verification'))
            ->first();
        
        if ($execution) {
            Log::info('[HyperVerge Webhook] Found ProcessorExecution', [
                'transaction_id' => $transactionId,
                'execution_id' => $execution->id,
                'processor_id' => $execution->processor_id,
                'job_id' => $execution->job_id,
            ]);
            
            return $execution;
        }
        
        // Fallback: try to find Contact by transaction_id
        $contact = Contact::where('kyc_transaction_id', $transactionId)->first();
        
        if ($contact) {
            Log::info('[HyperVerge Webhook] Found Contact', [
                'transaction_id' => $transactionId,
                'contact_id' => $contact->id,
            ]);
            
            return $contact;
        }
        
        Log::warning('[HyperVerge Webhook] No model found for transaction', [
            'transaction_id' => $transactionId,
        ]);
        
        return null;
    }
    
    /**
     * Handle approved KYC result.
     * 
     * Updates ProcessorExecution with approval data, stores images,
     * links to Contact, and resumes workflow.
     */
    protected function handleApproved(Model $model, KYCResultData $result): void
    {
        parent::handleApproved($model, $result);
        
        if ($model instanceof ProcessorExecution) {
            // Update ProcessorExecution output_data with KYC result
            $model->update([
                'output_data' => array_merge($model->output_data, [
                    'kyc_status' => 'approved',
                    'kyc_result' => [
                        'application_status' => $result->applicationStatus,
                        'face_match_score' => $result->modules[0]->faceMatchScore ?? null,
                        'liveness_score' => $result->modules[0]->livenessScore ?? null,
                        'name' => $result->modules[1]->extractedData['name'] ?? null,
                        'birth_date' => $result->modules[1]->extractedData['birth_date'] ?? null,
                        'id_number' => $result->modules[1]->extractedData['id_number'] ?? null,
                    ],
                    'approved_at' => now()->toIso8601String(),
                ])
            ]);
            
            Log::info('[HyperVerge Webhook] ProcessorExecution approved', [
                'execution_id' => $model->id,
                'transaction_id' => $model->output_data['transaction_id'],
            ]);
            
            // Store KYC images in ProcessorExecution media collections
            StoreKYCImages::run(
                model: $model,
                transactionId: $model->output_data['transaction_id']
            );
            
            // Link to Contact (create if needed)
            $this->linkToContact($model, $result);
            
            // Resume workflow - dispatch next processor in pipeline
            $this->resumeDocumentProcessing($model);
        }
        
        if ($model instanceof Contact) {
            // Update contact KYC status
            $model->update([
                'kyc_status' => 'approved',
                'kyc_completed_at' => now(),
            ]);
            
            Log::info('[HyperVerge Webhook] Contact approved', [
                'contact_id' => $model->id,
                'transaction_id' => $model->kyc_transaction_id,
            ]);
            
            // Store images in Contact media collections
            StoreKYCImages::run(
                model: $model,
                transactionId: $model->kyc_transaction_id
            );
        }
    }
    
    /**
     * Handle rejected KYC result.
     */
    protected function handleRejected(Model $model, KYCResultData $result, array $reasons): void
    {
        parent::handleRejected($model, $result, $reasons);
        
        if ($model instanceof ProcessorExecution) {
            $model->update([
                'output_data' => array_merge($model->output_data, [
                    'kyc_status' => 'rejected',
                    'rejection_reasons' => $reasons,
                    'rejected_at' => now()->toIso8601String(),
                ])
            ]);
            
            Log::warning('[HyperVerge Webhook] ProcessorExecution rejected', [
                'execution_id' => $model->id,
                'transaction_id' => $model->output_data['transaction_id'],
                'reasons' => $reasons,
            ]);
        }
        
        if ($model instanceof Contact) {
            $model->update([
                'kyc_status' => 'rejected',
                'kyc_rejection_reasons' => $reasons,
                'kyc_completed_at' => now(),
            ]);
            
            Log::warning('[HyperVerge Webhook] Contact rejected', [
                'contact_id' => $model->id,
                'transaction_id' => $model->kyc_transaction_id,
                'reasons' => $reasons,
            ]);
        }
    }
    
    /**
     * Link ProcessorExecution to Contact via contactables pivot.
     */
    protected function linkToContact(ProcessorExecution $execution, KYCResultData $result): void
    {
        $mobile = $execution->output_data['contact_mobile'] ?? null;
        
        if (!$mobile) {
            Log::debug('[HyperVerge Webhook] No mobile number in execution output_data, skipping contact link');
            return;
        }
        
        // Find or create contact
        $country = 'PH'; // Default, could be extracted from config
        $formattedMobile = phone($mobile, $country)->formatForMobileDialingInCountry($country);
        
        $contact = Contact::where('mobile', $formattedMobile)->first();
        
        if (!$contact) {
            $contact = Contact::create([
                'mobile' => $mobile,
                'name' => $result->modules[1]->extractedData['name'] ?? $execution->output_data['contact_name'] ?? null,
                'email' => $execution->output_data['contact_email'] ?? null,
                'kyc_transaction_id' => $execution->output_data['transaction_id'],
                'kyc_status' => 'approved',
                'kyc_completed_at' => now(),
            ]);
            
            Log::info('[HyperVerge Webhook] Created new Contact', [
                'contact_id' => $contact->id,
                'mobile' => $formattedMobile,
            ]);
        } else {
            // Update existing contact
            $contact->update([
                'kyc_transaction_id' => $execution->output_data['transaction_id'],
                'kyc_status' => 'approved',
                'kyc_completed_at' => now(),
            ]);
            
            Log::info('[HyperVerge Webhook] Updated existing Contact', [
                'contact_id' => $contact->id,
            ]);
        }
        
        // Link contact to execution via contactables pivot
        $execution->contacts()->syncWithoutDetaching([
            $contact->id => [
                'relationship_type' => 'signer',
                'metadata' => json_encode([
                    'kyc_result' => $execution->output_data['kyc_result'] ?? [],
                    'transaction_id' => $execution->output_data['transaction_id'],
                ])
            ]
        ]);
        
        // Update execution with contact reference
        $execution->update([
            'output_data' => array_merge($execution->output_data, [
                'contact_id' => $contact->id,
            ])
        ]);
        
        Log::info('[HyperVerge Webhook] Linked Contact to ProcessorExecution', [
            'contact_id' => $contact->id,
            'execution_id' => $execution->id,
        ]);
    }
    
    /**
     * Resume document processing workflow after KYC approval.
     */
    protected function resumeDocumentProcessing(ProcessorExecution $execution): void
    {
        // Dispatch event to resume workflow
        // The workflow system will handle dispatching the next processor
        event(new \App\Events\ProcessorExecutionCompleted($execution));
        
        Log::info('[HyperVerge Webhook] Resumed document processing', [
            'execution_id' => $execution->id,
            'job_id' => $execution->job_id,
        ]);
    }
}
