<?php

declare(strict_types=1);

namespace App\Actions\Documents\Web;

use App\Models\Campaign;
use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List documents with filtering and pagination.
 */
class ListDocuments
{
    use AsAction;

    /**
     * List documents.
     */
    public function handle(
        ?Campaign $campaign = null,
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Document::query()
            ->with(['campaign', 'documentJob'])
            ->latest();

        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('original_filename', 'ilike', "%{$search}%")
                    ->orWhere('uuid', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Handle as controller.
     */
    public function asController(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->handle(
            campaign: null,
            status: $status,
            search: $search,
            perPage: $perPage
        );
    }
}
