<?php

declare(strict_types=1);

namespace App\States\Campaign;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class CampaignState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftCampaignState::class)
            ->registerStatesFromDirectory(__DIR__)
            ->allowTransition(DraftCampaignState::class, ActiveCampaignState::class)
            ->allowTransition(ActiveCampaignState::class, PausedCampaignState::class)
            ->allowTransition(PausedCampaignState::class, ActiveCampaignState::class)
            ->allowTransition(ActiveCampaignState::class, ArchivedCampaignState::class)
            ->allowTransition(PausedCampaignState::class, ArchivedCampaignState::class)
            ->allowTransition(DraftCampaignState::class, ArchivedCampaignState::class);
    }
}
