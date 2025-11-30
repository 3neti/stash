<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('campaigns', CampaignController::class);

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{uuid}', [DocumentController::class, 'show'])->name('documents.show');
});

require __DIR__.'/settings.php';
