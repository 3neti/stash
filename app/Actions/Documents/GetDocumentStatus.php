<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Data\Api\Resources\DocumentData;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get Document Status Action
 *
 * Retrieves document details with processing status and job information.
 */
class GetDocumentStatus
{
    use AsAction;

    /**
     * Handle getting document status.
     *
     * @param  Document  $document  The document to retrieve
     * @return Document The document with loaded relationships
     */
    public function handle(Document $document): Document
    {
        // Eager load all necessary relationships for complete status
        return $document->load([
            'campaign',
            'documentJob.processorExecutions.processor',
        ]);
    }

    /**
     * Run as HTTP controller (by UUID).
     */
    public function asController(string $uuid): JsonResponse
    {
        // Find document by UUID
        $document = Document::where('uuid', $uuid)->firstOrFail();

        // Load relationships and get status
        $document = $this->handle($document);

        return response()->json(
            DocumentData::fromModel($document)->toArray()
        );
    }

    /**
     * Get authorization message for failed authorization.
     */
    public function getAuthorizationMessage(): string
    {
        return 'You are not authorized to view this document.';
    }
}
