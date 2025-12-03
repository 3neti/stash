<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Middleware\InitializeTenantFromUser;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified', InitializeTenantFromUser::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('campaigns', CampaignController::class);

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{uuid}', [DocumentController::class, 'show'])->name('documents.show');
});

// Webhook (no auth required)
Route::post('/webhooks/hyperverge', function (\Illuminate\Http\Request $request) {
    $webhookConfig = new \Spatie\WebhookClient\WebhookConfig([
        'name' => 'hyperverge',
        'signing_secret' => config('hyperverge.webhook.secret'),
        'signature_header_name' => 'X-HyperVerge-Signature',
        'signature_validator' => \LBHurtado\HyperVerge\Webhooks\HypervergeSignatureValidator::class,
        'webhook_profile' => \LBHurtado\HyperVerge\Webhooks\HypervergeWebhookProfile::class,
        'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => config('hyperverge.webhook.process_webhook_job'),
    ]);

    return (new \Spatie\WebhookClient\WebhookController)->__invoke($request, $webhookConfig);
})->name('webhooks.hyperverge');

require __DIR__.'/settings.php';
