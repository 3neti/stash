<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Campaigns\Web\CreateCampaign;
use App\Actions\Campaigns\Web\DeleteCampaign;
use App\Actions\Campaigns\Web\ListCampaigns;
use App\Actions\Campaigns\Web\ShowCampaign;
use App\Actions\Campaigns\Web\UpdateCampaign;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * Display campaigns list.
     */
    public function index(): Response
    {
        $campaigns = ListCampaigns::run(
            status: request('status'),
            search: request('search'),
            perPage: (int) request('per_page', 15)
        );

        return Inertia::render('campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => [
                'status' => request('status'),
                'search' => request('search'),
            ],
        ]);
    }

    /**
     * Show create campaign form.
     */
    public function create(): Response
    {
        return Inertia::render('campaigns/Create');
    }

    /**
     * Store new campaign.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = CreateCampaign::run($request->validated());

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign created successfully.');
    }

    /**
     * Display single campaign.
     */
    public function show(string $campaign): Response
    {
        $campaignModel = Campaign::findOrFail($campaign);
        $campaignData = ShowCampaign::run($campaignModel);

        return Inertia::render('campaigns/Show', [
            'campaign' => $campaignData,
        ]);
    }

    /**
     * Show edit campaign form.
     */
    public function edit(string $campaign): Response
    {
        $campaignModel = Campaign::findOrFail($campaign);

        return Inertia::render('campaigns/Edit', [
            'campaign' => $campaignModel,
        ]);
    }

    /**
     * Update campaign.
     */
    public function update(UpdateCampaignRequest $request, string $campaign): RedirectResponse
    {
        $campaignModel = Campaign::findOrFail($campaign);
        UpdateCampaign::run($campaignModel, $request->validated());

        return redirect()
            ->route('campaigns.show', $campaignModel)
            ->with('success', 'Campaign updated successfully.');
    }

    /**
     * Delete campaign.
     */
    public function destroy(string $campaign): RedirectResponse
    {
        $campaignModel = Campaign::findOrFail($campaign);
        DeleteCampaign::run($campaignModel);

        return redirect()
            ->route('campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }
}
