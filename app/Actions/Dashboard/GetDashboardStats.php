<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Campaign;
use App\Models\Document;
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
                'active' => Campaign::where('status', 'active')->count(),
                'draft' => Campaign::where('status', 'draft')->count(),
                'paused' => Campaign::where('status', 'paused')->count(),
                'archived' => Campaign::where('status', 'archived')->count(),
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
