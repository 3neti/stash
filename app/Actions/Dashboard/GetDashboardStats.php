<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Campaign;
use App\Models\Document;
use App\States\Campaign\ActiveCampaignState;
use App\States\Campaign\DraftCampaignState;
use App\States\Campaign\PausedCampaignState;
use App\States\Campaign\ArchivedCampaignState;
use App\States\Document\PendingDocumentState;
use App\States\Document\ProcessingDocumentState;
use App\States\Document\QueuedDocumentState;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get dashboard statistics.
 */
class GetDashboardStats
{
    use AsAction;

    /**
     * Get dashboard stats.
     */
    public function handle(): array
    {
        return [
            'campaigns' => [
                'total' => Campaign::count(),
                'active' => Campaign::whereState('state', ActiveCampaignState::class)->count(),
                'draft' => Campaign::whereState('state', DraftCampaignState::class)->count(),
                'paused' => Campaign::whereState('state', PausedCampaignState::class)->count(),
                'archived' => Campaign::whereState('state', ArchivedCampaignState::class)->count(),
            ],
            'documents' => [
                'total' => Document::count(),
                'pending' => Document::whereState('state', PendingDocumentState::class)->count(),
                'processing' => Document::whereState('state', [QueuedDocumentState::class, ProcessingDocumentState::class])->count(),
                'completed' => Document::whereState('state', CompletedDocumentState::class)->count(),
                'failed' => Document::whereState('state', FailedDocumentState::class)->count(),
            ],
        ];
    }

    /**
     * Handle as controller.
     */
    public function asController(): array
    {
        return $this->handle();
    }
}
