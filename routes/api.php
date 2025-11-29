<?php

declare(strict_types=1);

use App\Actions\Documents\GetDocumentStatus;
use App\Actions\Documents\ListDocuments;
use App\Actions\Documents\UploadDocument;
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

// Document Ingestion API
Route::prefix('campaigns/{campaign}')->group(function () {
    // Upload document to campaign
    Route::post('documents', UploadDocument::class)
        ->name('api.campaigns.documents.store');
    
    // List documents for campaign
    Route::get('documents', ListDocuments::class)
        ->name('api.campaigns.documents.index');
});

// Document status (by UUID, not scoped to campaign)
Route::get('documents/{uuid}', GetDocumentStatus::class)
    ->name('api.documents.show')
    ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
