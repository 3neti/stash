<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant;
use App\Services\TenantOnboardingService;
use Illuminate\Support\Facades\Log;

class TenantObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        private TenantOnboardingService $onboardingService,
    ) {}

    /**
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        // Check if auto-onboarding is enabled
        if (! config('app.tenant_auto_onboarding', true)) {
            Log::info("Auto-onboarding disabled, skipping for tenant: {$tenant->name}");
            return;
        }

        Log::info("Tenant created, triggering onboarding: {$tenant->name}");

        $this->onboardingService->onboard($tenant);
    }
}
