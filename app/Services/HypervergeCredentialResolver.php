<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\Credential;
use App\Models\Processor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LBHurtado\HyperVerge\Contracts\CredentialResolverInterface;
use LBHurtado\HyperVerge\Data\HypervergeCredentials;

/**
 * Resolves HyperVerge credentials using Stash's Credential model.
 * 
 * Precedence order (using Stash's hierarchical credential system):
 * 1. Processor-level credentials
 * 2. Campaign-level credentials
 * 3. System-level credentials (null credentialable)
 * 4. Environment credentials (from config/hyperverge.php)
 */
class HypervergeCredentialResolver implements CredentialResolverInterface
{
    /**
     * Resolve credentials based on context model.
     * 
     * @param Model|null $context Campaign, Document, or Processor
     * @return HypervergeCredentials Resolved credentials
     */
    public function resolve(?Model $context = null): HypervergeCredentials
    {
        if ($context === null) {
            return $this->resolveFromEnvironment();
        }

        // Extract processor and campaign from context
        $processor = $this->extractProcessor($context);
        $campaign = $this->extractCampaign($context);

        // Try to resolve from Stash's Credential model
        $credentials = Credential::resolve(
            key: 'hyperverge',
            processor: $processor,
            campaign: $campaign
        );

        if ($credentials && $credentials->isActive()) {
            Log::debug('[HypervergeCredentialResolver] Using database credentials', [
                'scope' => $credentials->credentialable_type 
                    ? class_basename($credentials->credentialable_type) 
                    : 'system',
                'credentialable_id' => $credentials->credentialable_id,
            ]);

            return $this->buildFromCredential($credentials);
        }

        // Fall back to environment credentials
        return $this->resolveFromEnvironment();
    }

    /**
     * Extract Processor model from context.
     */
    protected function extractProcessor(Model $context): ?Processor
    {
        if ($context instanceof Processor) {
            return $context;
        }

        // Could extend to extract processor from DocumentJob/ProcessorExecution
        // if ($context instanceof ProcessorExecution) {
        //     return $context->processor;
        // }

        return null;
    }

    /**
     * Extract Campaign model from context.
     */
    protected function extractCampaign(Model $context): ?Campaign
    {
        if ($context instanceof Campaign) {
            return $context;
        }

        // Extract from Document
        if ($context instanceof \App\Models\Document) {
            return $context->campaign;
        }

        return null;
    }

    /**
     * Build HypervergeCredentials from Stash Credential model.
     */
    protected function buildFromCredential(Credential $credential): HypervergeCredentials
    {
        $metadata = $credential->metadata ?? [];
        
        // Credential.value contains JSON with app_id, app_key, etc.
        $data = json_decode($credential->value, true) ?? [];

        return new HypervergeCredentials(
            appId: $data['app_id'] ?? config('hyperverge.app_id'),
            appKey: $data['app_key'] ?? config('hyperverge.app_key'),
            baseUrl: $data['base_url'] ?? config('hyperverge.base_url'),
            workflowId: $data['workflow_id'] ?? $metadata['workflow_id'] ?? config('hyperverge.url_workflow')
        );
    }

    /**
     * Resolve credentials from environment/config.
     */
    protected function resolveFromEnvironment(): HypervergeCredentials
    {
        Log::debug('[HypervergeCredentialResolver] Using environment credentials');
        
        return HypervergeCredentials::fromConfig();
    }
}
