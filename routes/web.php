<?php

use App\Http\Controllers\CatalogController;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('properti', [CatalogController::class, 'index'])->name('properties.index');
Route::get('properti/{slug}', [CatalogController::class, 'show'])->name('properties.show');

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
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
});

require __DIR__.'/settings.php';
