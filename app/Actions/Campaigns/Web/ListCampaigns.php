<?php

declare(strict_types=1);

namespace App\Actions\Campaigns\Web;

use App\Models\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List campaigns for web interface.
 */
class ListCampaigns
{
    use AsAction;

    /**
     * List campaigns with filters and pagination.
     */
    public function handle(
        ?string $status = null,
        ?string $search = null,
        int $perPage = 12
    ): LengthAwarePaginator {
        $query = Campaign::query()
            ->withCount('documents')
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle(
            $request->input('status'),
            $request->input('search'),
            (int) $request->input('per_page', 12)
        );
    }
}
