<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Data\Api\Resources\DocumentData;
use App\Models\Campaign;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;
use App\States\Document\PendingDocumentState;
use App\States\Document\ProcessingDocumentState;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List Documents Action
 * 
 * Lists documents for a campaign with filtering and pagination.
 */
class ListDocuments
{
    use AsAction;

    /**
     * Handle listing documents with filters.
     * 
     * @param  Campaign  $campaign  The campaign to list documents from
     * @param  string|null  $status  Filter by status (pending, processing, completed, failed)
     * @param  Carbon|null  $dateFrom  Filter by created_at >= date
     * @param  Carbon|null  $dateTo  Filter by created_at <= date
     * @param  int  $perPage  Results per page (default 15, max 100)
     * @return LengthAwarePaginator  Paginated documents
     */
    public function handle(
        Campaign $campaign,
        ?string $status = null,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        // Start query with eager loading
        $query = $campaign->documents()
            ->with(['documentJob.processorExecutions.processor'])
            ->orderByDesc('created_at');
        
        // Apply status filter
        if ($status) {
            $stateClass = $this->getStateClass($status);
            $query->whereState('state', $stateClass);
        }
        
        // Apply date range filters
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo->endOfDay());
        }
        
        // Paginate with max limit of 100
        return $query->paginate(min($perPage, 100));
    }

    /**
     * Run as HTTP controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Parse request parameters
        $status = $validatedData['status'] ?? null;
        $dateFrom = isset($validatedData['date_from']) 
            ? Carbon::parse($validatedData['date_from']) 
            : null;
        $dateTo = isset($validatedData['date_to']) 
            ? Carbon::parse($validatedData['date_to']) 
            : null;
        $perPage = (int) ($validatedData['per_page'] ?? 15);
        
        // Get paginated documents
        $documents = $this->handle(
            $campaign,
            $status,
            $dateFrom,
            $dateTo,
            $perPage
        );
        
        // Transform to DTOs
        return response()->json([
            'data' => $documents->map(fn ($doc) => DocumentData::fromModel($doc)->toArray()),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ],
            'links' => [
                'first' => $documents->url(1),
                'last' => $documents->url($documents->lastPage()),
                'prev' => $documents->previousPageUrl(),
                'next' => $documents->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get state class from status string.
     */
    private function getStateClass(string $status): string
    {
        return match ($status) {
            'pending' => PendingDocumentState::class,
            'processing' => ProcessingDocumentState::class,
            'completed' => CompletedDocumentState::class,
            'failed' => FailedDocumentState::class,
            default => throw new \InvalidArgumentException("Invalid status: {$status}")
        };
    }

    /**
     * Validation rules for request parameters.
     */
    public static function rules(): array
    {
        return [
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get authorization message for failed authorization.
     */
    public function getAuthorizationMessage(): string
    {
        return 'You are not authorized to view documents for this campaign.';
    }
}
