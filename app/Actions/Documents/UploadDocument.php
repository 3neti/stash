<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Data\Api\Resources\DocumentData;
use App\Jobs\Pipeline\ProcessDocumentJob;
use App\Models\Campaign;
use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Uid\Ulid;

/**
 * Upload Document Action
 *
 * Handles document file upload, storage, and pipeline processing initiation.
 */
class UploadDocument
{
    use AsAction;

    /**
     * Validation rules for the action.
     */
    public static function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,tiff|max:10240', // 10MB
            'metadata' => 'nullable|array',
            'metadata.description' => 'nullable|string|max:500',
            'metadata.reference_id' => 'nullable|string|max:100',
        ];
    }

    /**
     * Handle the document upload.
     *
     * @param  Campaign  $campaign  The campaign to associate the document with
     * @param  UploadedFile  $file  The uploaded file
     * @param  array|null  $metadata  Optional metadata for the document
     * @return Document  The created document
     */
    public function handle(
        Campaign $campaign,
        UploadedFile $file,
        ?array $metadata = null
    ): Document {
        // 1. Generate unique document ID
        $documentId = (string) new Ulid();

        // 2. Calculate file hash for integrity
        $hash = hash_file('sha256', $file->getRealPath());

        // 3. Generate storage path
        $storagePath = $this->getTenantStoragePath($campaign, $documentId, $file);

        // 4. Store file to tenant disk
        $file->storeAs(
            dirname($storagePath),
            basename($storagePath),
            'tenant'
        );

        // 5. Create Document record
        $document = Document::create([
            'id' => $documentId,
            'uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_path' => $storagePath,
            'storage_disk' => 'tenant',
            'hash' => $hash,
            'metadata' => $metadata ?? [],
        ]);

        // 6. Dispatch pipeline processing job
        ProcessDocumentJob::dispatch($document);

        return $document;
    }

    /**
     * Run as HTTP controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): JsonResponse
    {
        $validatedData = $request->validated();

        $document = $this->handle(
            $campaign,
            $validatedData['file'],
            $validatedData['metadata'] ?? null
        );

        // Eager load the document job for response
        $document->load('documentJob');

        return response()->json(
            DocumentData::fromModel($document)->toArray(),
            201
        );
    }

    /**
     * Generate tenant-scoped storage path for the document.
     *
     * Format: tenants/{tenant_id}/documents/{year}/{month}/{document_id}_{filename}
     */
    private function getTenantStoragePath(
        Campaign $campaign,
        string $documentId,
        UploadedFile $file
    ): string {
        $tenant = TenantContext::current();
        $date = now();

        // Sanitize filename (remove special characters, keep extension)
        $extension = $file->getClientOriginalExtension();
        $sanitizedName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = $documentId . '_' . $sanitizedName . '.' . $extension;

        // For tests, when TenantContext is null, use campaign's tenant relationship
        $tenantId = $tenant?->id ?? $campaign->tenant_id ?? 'test';

        return sprintf(
            'tenants/%s/documents/%s/%s/%s',
            $tenantId,
            $date->format('Y'),
            $date->format('m'),
            $filename
        );
    }

    /**
     * Get authorization message for failed authorization.
     */
    public function getAuthorizationMessage(): string
    {
        return 'You are not authorized to upload documents to this campaign.';
    }
}
