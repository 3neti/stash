<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\HyperVerge\Contracts\VerificationUrlResolver;

/**
 * Document Verification URL Resolver
 * 
 * Generates verification URLs for signed documents.
 * Used by HyperVerge's MarkDocumentWithKYC action.
 */
class DocumentVerificationUrlResolver implements VerificationUrlResolver
{
    /**
     * Resolve verification URL for a model and transaction.
     * 
     * @param Model $model Document model
     * @param string|null $transactionId KYC transaction ID
     * @return string Verification URL
     */
    public function resolve(Model $model, ?string $transactionId = null): string
    {
        // Generate verification URL
        // Format: /documents/verify/{uuid}/{transactionId}
        if (isset($model->uuid)) {
            return url("/documents/verify/{$model->uuid}/{$transactionId}");
        }
        
        // Fallback
        return url("/verify/{$transactionId}");
    }
}
