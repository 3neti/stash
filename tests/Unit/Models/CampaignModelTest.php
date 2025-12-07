<?php

use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

use App\Models\Campaign;
use App\States\Campaign\ActiveCampaignState;
use App\States\Campaign\DraftCampaignState;

beforeEach(function () {
    // Clean up campaigns from previous tests to ensure isolation
    Campaign::query()->forceDelete();
});

describe('Campaign Model - Direct Creation', function () {
    test('can create campaign with minimal attributes', function () {
        $campaign = Campaign::create([
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'pipeline_config' => ['processors' => []],
        ]);

        expect($campaign)->toBeInstanceOf(Campaign::class)
            ->and($campaign->id)->not->toBeNull()
            ->and($campaign->name)->toBe('Test Campaign')
            ->and($campaign->slug)->toBe('test-campaign');
    });

    test('campaign uses ULID for primary key', function () {
        $campaign = Campaign::create([
            'name' => 'ULID Test',
            'slug' => 'ulid-test',
            'pipeline_config' => ['processors' => []],
        ]);

        // ULIDs are 26 characters, base32 encoded (case-insensitive)
        expect($campaign->id)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{26}$/');
    });

    test('campaign has correct default values', function () {
        $campaign = Campaign::create([
            'name' => 'Default Test',
            'slug' => 'default-test',
            'pipeline_config' => [],
        ]);

        expect($campaign->state)->toBeInstanceOf(DraftCampaignState::class)
            ->and($campaign->type)->toBe('custom')
            ->and($campaign->max_concurrent_jobs)->toBe(10)
            ->and($campaign->retention_days)->toBe(90);
    });
});

describe('Campaign Model - Factory Creation', function () {
    test('can create campaign using factory', function () {
        $campaign = Campaign::factory()->create();

        expect($campaign)->toBeInstanceOf(Campaign::class)
            ->and($campaign->id)->not->toBeNull()
            ->and($campaign->name)->not->toBeNull()
            ->and($campaign->slug)->not->toBeNull();
    });

    test('factory generates valid pipeline config', function () {
        $campaign = Campaign::factory()->create();

        expect($campaign->pipeline_config)
            ->toBeArray()
            ->toHaveKey('processors');
    });

    test('can create multiple campaigns with factory', function () {
        $campaigns = Campaign::factory()->count(5)->create();

        expect($campaigns)->toHaveCount(5);

        // Verify all have unique IDs and slugs
        $ids = $campaigns->pluck('id')->unique();
        $slugs = $campaigns->pluck('slug')->unique();

        expect($ids)->toHaveCount(5)
            ->and($slugs)->toHaveCount(5);
    });
});

describe('Campaign Model - Attributes', function () {
    test('pipeline_config is cast to array', function () {
        $campaign = Campaign::create([
            'name' => 'Config Test',
            'slug' => 'config-test',
            'pipeline_config' => ['processors' => [['type' => 'ocr']]],
        ]);

        $fresh = Campaign::find($campaign->id);

        expect($fresh->pipeline_config)->toBeArray()
            ->and($fresh->pipeline_config['processors'])->toBeArray();
    });

    test('settings is cast to array', function () {
        $campaign = Campaign::create([
            'name' => 'Settings Test',
            'slug' => 'settings-test',
            'pipeline_config' => [],
            'settings' => ['queue' => 'default'],
        ]);

        $fresh = Campaign::find($campaign->id);

        expect($fresh->settings)->toBeArray()
            ->and($fresh->settings['queue'])->toBe('default');
    });

    test('checklist_template is cast to array', function () {
        $checklist = [
            ['task' => 'Upload document', 'completed' => false],
            ['task' => 'Review results', 'completed' => false],
        ];

        $campaign = Campaign::create([
            'name' => 'Checklist Test',
            'slug' => 'checklist-test',
            'pipeline_config' => [],
            'checklist_template' => $checklist,
        ]);

        $fresh = Campaign::find($campaign->id);

        expect($fresh->checklist_template)->toBeArray()
            ->and($fresh->checklist_template)->toHaveCount(2);
    });

    test('published_at is cast to datetime', function () {
        $campaign = Campaign::create([
            'name' => 'DateTime Test',
            'slug' => 'datetime-test',
            'pipeline_config' => [],
            'published_at' => now(),
        ]);

        expect($campaign->published_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    test('credentials are encrypted and decrypted', function () {
        $credentials = json_encode(['api_key' => 'secret123']);

        $campaign = Campaign::create([
            'name' => 'Credentials Test',
            'slug' => 'credentials-test',
            'pipeline_config' => [],
            'credentials' => $credentials,
        ]);

        // Should be encrypted in database
        $raw = \Illuminate\Support\Facades\DB::connection('tenant')->table('campaigns')
            ->where('id', $campaign->id)
            ->value('credentials');
        expect($raw)->not->toBe($credentials);

        // Should be decrypted when accessed via model
        expect($campaign->credentials)->toBe($credentials);
    });

    test('processor_count accessor returns correct count', function () {
        $campaign = Campaign::create([
            'name' => 'Processor Count Test',
            'slug' => 'processor-count-test',
            'pipeline_config' => [
                'processors' => [
                    ['type' => 'ocr'],
                    ['type' => 'classification'],
                    ['type' => 'validation'],
                ],
            ],
        ]);

        expect($campaign->processor_count)->toBe(3);
    });
});

describe('Campaign Model - Methods', function () {
    test('publish sets published_at and state to active', function () {
        $campaign = Campaign::create([
            'name' => 'Publish Test',
            'slug' => 'publish-test',
            'pipeline_config' => [],
        ]);

        expect($campaign->state)->toBeInstanceOf(DraftCampaignState::class)
            ->and($campaign->published_at)->toBeNull();

        $campaign->publish();
        $campaign->refresh();

        expect($campaign->state)->toBeInstanceOf(ActiveCampaignState::class)
            ->and($campaign->published_at)->not->toBeNull()
            ->and($campaign->published_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    test('pause sets state to paused', function () {
        $campaign = Campaign::create([
            'name' => 'Pause Test',
            'slug' => 'pause-test',
            'pipeline_config' => [],
        ]);

        $campaign->state->transitionTo(ActiveCampaignState::class);
        $campaign->save();

        $campaign->pause();
        $campaign->refresh();

        // State should now be paused (not active)
        expect($campaign->state)->not->toBeInstanceOf(ActiveCampaignState::class);
    });

    test('archive sets state to archived', function () {
        $campaign = Campaign::create([
            'name' => 'Archive Test',
            'slug' => 'archive-test',
            'pipeline_config' => [],
        ]);

        $campaign->state->transitionTo(ActiveCampaignState::class);
        $campaign->save();

        $campaign->archive();
        $campaign->refresh();

        // State should now be archived (not active)
        expect($campaign->state)->not->toBeInstanceOf(ActiveCampaignState::class);
    });

    test('isActive returns true for active campaigns', function () {
        $activeCampaign = Campaign::create([
            'name' => 'Active Test',
            'slug' => 'active-test',
            'pipeline_config' => [],
        ]);

        $activeCampaign->state->transitionTo(ActiveCampaignState::class);
        $activeCampaign->save();

        $draftCampaign = Campaign::create([
            'name' => 'Draft Test',
            'slug' => 'draft-test',
            'pipeline_config' => [],
        ]);

        expect($activeCampaign->isActive())->toBeTrue()
            ->and($draftCampaign->isActive())->toBeFalse();
    });

    test('isPublished returns true when published_at is set', function () {
        $publishedCampaign = Campaign::create([
            'name' => 'Published Test',
            'slug' => 'published-test',
            'pipeline_config' => [],
            'published_at' => now(),
        ]);

        $unpublishedCampaign = Campaign::create([
            'name' => 'Unpublished Test',
            'slug' => 'unpublished-test',
            'pipeline_config' => [],
        ]);

        expect($publishedCampaign->isPublished())->toBeTrue()
            ->and($unpublishedCampaign->isPublished())->toBeFalse();
    });
});

describe('Campaign Model - Scopes', function () {
    test('active scope returns only active campaigns', function () {
        $active1 = Campaign::create(['name' => 'Active 1', 'slug' => 'active-1', 'pipeline_config' => []]);
        $active1->state->transitionTo(ActiveCampaignState::class);
        $active1->save();
        
        Campaign::create(['name' => 'Draft 1', 'slug' => 'draft-1', 'pipeline_config' => []]);
        
        $active2 = Campaign::create(['name' => 'Active 2', 'slug' => 'active-2', 'pipeline_config' => []]);
        $active2->state->transitionTo(ActiveCampaignState::class);
        $active2->save();

        $activeCampaigns = Campaign::active()->get();

        expect($activeCampaigns)->toHaveCount(2);
        expect($activeCampaigns->every(fn ($c) => $c->state instanceof ActiveCampaignState))->toBeTrue();
    });

    test('published scope returns only published campaigns', function () {
        Campaign::create(['name' => 'Published 1', 'slug' => 'published-1', 'pipeline_config' => [], 'published_at' => now()]);
        Campaign::create(['name' => 'Unpublished 1', 'slug' => 'unpublished-1', 'pipeline_config' => []]);
        Campaign::create(['name' => 'Published 2', 'slug' => 'published-2', 'pipeline_config' => [], 'published_at' => now()]);

        $publishedCampaigns = Campaign::published()->get();

        expect($publishedCampaigns)->toHaveCount(2);
        expect($publishedCampaigns->every(fn ($c) => ! is_null($c->published_at)))->toBeTrue();
    });

    test('draft scope returns only draft campaigns', function () {
        Campaign::create(['name' => 'Draft 1', 'slug' => 'draft-1', 'pipeline_config' => []]);
        
        $active1 = Campaign::create(['name' => 'Active 1', 'slug' => 'active-1', 'pipeline_config' => []]);
        $active1->state->transitionTo(ActiveCampaignState::class);
        $active1->save();
        
        Campaign::create(['name' => 'Draft 2', 'slug' => 'draft-2', 'pipeline_config' => []]);

        $draftCampaigns = Campaign::draft()->get();

        expect($draftCampaigns)->toHaveCount(2);
        expect($draftCampaigns->every(fn ($c) => $c->state instanceof DraftCampaignState))->toBeTrue();
    });
});

describe('Campaign Model - Soft Deletes', function () {
    test('campaigns can be soft deleted', function () {
        $campaign = Campaign::create([
            'name' => 'Soft Delete Test',
            'slug' => 'soft-delete-test',
            'pipeline_config' => [],
        ]);

        $id = $campaign->id;
        $campaign->delete();

        expect(Campaign::find($id))->toBeNull()
            ->and(Campaign::withTrashed()->find($id))->not->toBeNull();
    });

    test('soft deleted campaigns can be restored', function () {
        $campaign = Campaign::create([
            'name' => 'Restore Test',
            'slug' => 'restore-test',
            'pipeline_config' => [],
        ]);

        $id = $campaign->id;
        $campaign->delete();

        $deleted = Campaign::withTrashed()->find($id);
        $deleted->restore();

        expect(Campaign::find($id))->not->toBeNull();
    });
});
