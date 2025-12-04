<?php

declare(strict_types=1);

use App\Actions\Campaigns\GenerateCampaignToken;
use App\Actions\Campaigns\ListWebhookDeliveries;
use App\Actions\Campaigns\RevokeCampaignToken;
use App\Actions\Campaigns\SetCampaignChannel;
use App\Actions\Campaigns\TestCampaignWebhook;
use App\Actions\Documents\GetDocumentStatus;
use App\Actions\Documents\ListDocuments;
use App\Actions\Documents\UploadDocument;
use App\Http\Controllers\API\DocumentProgressController;
use App\Http\Controllers\Api\ProcessorArtifactController;
use App\Http\Controllers\API\KycContactController;
use App\Http\Middleware\InitializeTenantFromUser;
use App\Models\Campaign;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

// Token Management (requires web auth)
Route::middleware('auth:sanctum')->prefix('campaigns/{campaign}')->group(function () {
    Route::post('tokens', GenerateCampaignToken::class)
        ->name('api.campaigns.tokens.store');

    Route::delete('tokens', RevokeCampaignToken::class)
        ->name('api.campaigns.tokens.destroy');
});

// Channel Management (requires web auth)
Route::middleware('auth:sanctum')->prefix('campaigns/{campaign}')->group(function () {
    Route::put('channels', SetCampaignChannel::class)
        ->name('api.campaigns.channels.update');

    Route::post('webhook/test', TestCampaignWebhook::class)
        ->name('api.campaigns.webhook.test');

    Route::get('webhooks', ListWebhookDeliveries::class)
        ->name('api.campaigns.webhooks.index');
});

// Document Ingestion API (requires API token)
Route::middleware(['auth:sanctum', 'throttle:api', InitializeTenantFromUser::class])->prefix('campaigns/{campaign}')->group(function () {
    // Upload document to campaign
    Route::post('documents', UploadDocument::class)
        ->middleware('throttle:api-uploads')
        ->name('api.campaigns.documents.store');

    // List documents for campaign
    Route::get('documents', ListDocuments::class)
        ->name('api.campaigns.documents.index');
});

// Document status (by UUID, not scoped to campaign)
Route::middleware(['auth:sanctum', 'throttle:api'])->get('documents/{uuid}', GetDocumentStatus::class)
    ->name('api.documents.show')
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// Document progress (real-time progress tracking)
Route::middleware(['auth:sanctum', 'throttle:api', InitializeTenantFromUser::class])->get('documents/{uuid}/progress', [DocumentProgressController::class, 'show'])
    ->name('api.documents.progress.show')
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// Document metrics (processor execution metrics)
Route::middleware(['auth:sanctum', 'throttle:api', InitializeTenantFromUser::class])->get('documents/{uuid}/metrics', [DocumentProgressController::class, 'metrics'])
    ->name('api.documents.metrics.index')
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// KYC Contact Data (public endpoint - no auth, secured by transaction ID)
Route::get('/kyc/{transactionId}/contact', KycContactController::class)
    ->name('api.kyc.contact');

// Processor Artifacts API
Route::middleware(['auth:sanctum', 'throttle:api', InitializeTenantFromUser::class])->group(function () {
    // List all artifacts for a document
    Route::get('documents/{document}/artifacts', [ProcessorArtifactController::class, 'documentArtifacts'])
        ->name('api.documents.artifacts.index');

    // List artifacts by collection for a specific processor execution
    Route::get('processor-executions/{execution}/artifacts/{collection}', [ProcessorArtifactController::class, 'executionArtifactsByCollection'])
        ->name('api.processor-executions.artifacts.collection');

    // Get single artifact metadata
    Route::get('processor-executions/{execution}/artifacts/{media}', [ProcessorArtifactController::class, 'show'])
        ->name('api.processor-executions.artifacts.show');

    // Download artifact file
    Route::get('processor-executions/{execution}/artifacts/{media}/download', [ProcessorArtifactController::class, 'download'])
        ->name('api.processor-executions.artifacts.download');
});
