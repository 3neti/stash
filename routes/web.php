<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\KycCallbackController;
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

// KYC Callback (no auth required - public endpoint for HyperVerge redirect)
// Note: This handles GET callback redirects from browser, not POST webhooks
Route::get('/kyc/callback/{documentUuid}', KycCallbackController::class)
    ->name('kyc.callback');

// Signed Document Download (public endpoint - no auth required)
Route::get('/documents/signed/{tenant}/{execution}/download', [DocumentController::class, 'downloadSigned'])
    ->name('documents.signed.download');

// Document Verification (public endpoint - no auth required)
Route::get('/documents/verify/{documentUuid}/{transactionId}', DocumentVerificationController::class)
    ->name('documents.verify');

require __DIR__.'/settings.php';
