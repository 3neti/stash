<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Data\Api\Resources\DocumentData;
use App\Models\Campaign;
use App\Models\Document;
use App\Services\Pipeline\DocumentProcessingPipeline;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            'file' => 'required_without_all:files,documents|file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
            'files' => 'required_without_all:file,documents|array|max:10',
            'files.*' => 'file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
            'documents' => 'required_without_all:file,files|array|max:10',
            'documents.*' => 'file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
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
     * @return Document The created document
     *
     * @throws \Illuminate\Validation\ValidationException If file validation fails
     */
    public function handle(
        Campaign $campaign,
        UploadedFile $file,
        ?array $metadata = null
    ): Document {
        Log::debug('[UploadDocument] Handling document upload', [
            'campaign_id' => $campaign->id,
            'filename' => $file->getClientOriginalName(),
            'tenant_context' => TenantContext::current()?->id,
            'connection' => app('db')->getDefaultConnection(),
        ]);

        // Validate file against campaign-specific rules
        $this->validateFile($file, $campaign);

        // 1. Generate unique document ID
        $documentId = (string) new Ulid;

        // 2. Calculate file hash for integrity
        $hash = hash_file('sha256', $file->getRealPath());

        // 3. Generate storage path
        $storagePath = $this->getTenantStoragePath($campaign, $documentId, $file);
        Log::debug('[UploadDocument] Generated storage path', ['path' => $storagePath]);

        // 4. Store file to tenant disk
        $file->storeAs(
            dirname($storagePath),
            basename($storagePath),
            'tenant'
        );
        Log::debug('[UploadDocument] File stored');

        // 5. Create Document record
        Log::debug('[UploadDocument] Creating document record', ['document_id' => $documentId]);
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
        Log::debug('[UploadDocument] Document record created');

        // 6. Start pipeline processing via DocumentProcessingPipeline
        Log::debug('[UploadDocument] Starting pipeline processing');
        $pipeline = app(DocumentProcessingPipeline::class);
        $pipeline->process($document, $campaign);
        Log::debug('[UploadDocument] Pipeline processing started');

        return $document;
    }

    /**
     * Run as HTTP controller.
     */
    public function asController(ActionRequest $request, string $campaign): JsonResponse
    {
        // Manually retrieve the campaign instead of using route model binding
        $campaignModel = Campaign::findOrFail($campaign);

        $validatedData = $request->validated();

        // Normalize files array to support both 'documents' and 'files'
        $files = $validatedData['documents'] ?? $validatedData['files'] ?? null;

        // Batch upload if an array of files is present
        if (is_array($files)) {
            return $this->handleBatchUpload($campaignModel, $files, $validatedData['metadata'] ?? null);
        }

        // Single file upload
        $file = $validatedData['file'] ?? null;
        if (! $file) {
            return response()->json([
                'message' => 'No file provided.',
                'errors' => ['file' => ['A file is required when no files/documents array is provided.']],
            ], 422);
        }

        $document = $this->handle(
            $campaignModel,
            $file,
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
     * Handle batch file upload.
     */
    private function handleBatchUpload(
        Campaign $campaign,
        array $files,
        ?array $metadata = null
    ): JsonResponse {
        $successful = [];
        $failed = [];

        foreach ($files as $index => $file) {
            try {
                $document = $this->handle($campaign, $file, $metadata);
                $document->load('documentJob');
                $successful[] = DocumentData::fromModel($document)->toArray();
            } catch (\Exception $e) {
                $failed[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $statusCode = match (true) {
            count($failed) === 0 => 201, // All successful
            count($successful) === 0 => 422, // All failed
            default => 207, // Partial success (Multi-Status)
        };

        return response()->json([
            'successful' => $successful,
            'failed' => $failed,
            'summary' => [
                'total' => count($files),
                'successful' => count($successful),
                'failed' => count($failed),
            ],
        ], $statusCode);
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
        $filename = $documentId.'_'.$sanitizedName.'.'.$extension;

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
     * Validate file against campaign-specific rules.
     *
     * @param  UploadedFile  $file  The file to validate
     * @param  Campaign  $campaign  The campaign with file rules
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateFile(UploadedFile $file, Campaign $campaign): void
    {
        $mimeType = $file->getMimeType();
        $fileSizeBytes = $file->getSize();

        // Validate MIME type
        if (! $campaign->acceptsMimeType($mimeType)) {
            $allowedExtensions = implode(', ', $campaign->getAcceptedExtensions());
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => sprintf(
                    'The file must be a file of type: %s. Uploaded file type: %s',
                    $allowedExtensions,
                    $mimeType
                ),
            ]);
        }

        // Validate file size
        if ($fileSizeBytes > $campaign->getMaxFileSizeBytes()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => sprintf(
                    'The file must not be greater than %s MB. Uploaded file size: %s MB',
                    $campaign->getMaxFileSizeMB(),
                    round($fileSizeBytes / 1048576, 2)
                ),
            ]);
        }

        Log::debug('[UploadDocument] File validation passed', [
            'mime_type' => $mimeType,
            'size_bytes' => $fileSizeBytes,
            'campaign_allowed_mime_types' => $campaign->getAllowedMimeTypes(),
            'campaign_max_size_bytes' => $campaign->getMaxFileSizeBytes(),
        ]);
    }

    /**
     * Get authorization message for failed authorization.
     */
    public function getAuthorizationMessage(): string
    {
        return 'You are not authorized to upload documents to this campaign.';
    }
}
