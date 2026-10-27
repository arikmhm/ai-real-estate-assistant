<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('staff', 'pages::staff.index')
        ->middleware('can:viewAny,'.User::class)
        ->name('staff.index');
});

require __DIR__.'/settings.php';
