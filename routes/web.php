<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Models\Listing;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('properti', [CatalogController::class, 'index'])->name('properties.index');
Route::get('properti/{slug}', [CatalogController::class, 'show'])->name('properties.show');
Route::post('assistant/message', [AssistantController::class, 'message'])->name('assistant.message');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::livewire('staff', 'pages::staff.index')
        ->middleware('can:viewAny,'.User::class)
        ->name('staff.index');
    Route::livewire('listings', 'pages::listings.index')
        ->middleware('can:viewAny,'.Listing::class)->name('listings.index');
    Route::livewire('listings/create', 'pages::listings.editor')
        ->middleware('can:create,'.Listing::class)->name('listings.create');
    Route::livewire('listings/{listing:slug}/edit', 'pages::listings.editor')
        ->middleware('can:update,listing')->name('listings.edit');
    Route::livewire('listings/{listing:slug}', 'pages::listings.show')
        ->middleware('can:view,listing')->name('listings.show');
    Route::livewire('prospects', 'pages::prospects.index')
        ->middleware('can:viewAny,'.Prospect::class)->name('prospects.index');
    Route::livewire('prospects/create', 'pages::prospects.create')
        ->middleware('can:create,'.Prospect::class)->name('prospects.create');
    Route::livewire('prospects/{prospect}', 'pages::prospects.show')
        ->middleware('can:view,prospect')->name('prospects.show');
    Route::livewire('visits', 'pages::visits.index')
        ->middleware('can:viewAny,'.Prospect::class)->name('visits.index');
});

require __DIR__.'/settings.php';
