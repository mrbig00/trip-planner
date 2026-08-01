<?php

use Livewire\Volt\Volt;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SocialiteController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
        ->whereIn('provider', ['google'])
        ->name('socialite.redirect');

    Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])
        ->whereIn('provider', ['google'])
        ->name('socialite.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('trips', 'trips.index')->name('trips.index');
    Volt::route('trips/create', 'trips.create')->name('trips.create');
    Volt::route('trips/{trip}', 'trips.show')->name('trips.show');
    Volt::route('trips/{trip}/edit', 'trips.edit')->name('trips.edit');

    // Location routes
    Volt::route('trips/{trip}/locations/create', 'locations.create')->name('locations.create');
    Volt::route('trips/{trip}/locations/{location}/edit', 'locations.edit')->name('locations.edit');

    // Expense routes
    Volt::route('trips/{trip}/expenses/create', 'expenses.create')->name('expenses.create');
    Volt::route('trips/{trip}/expenses/{expense}/edit', 'expenses.edit')->name('expenses.edit');
});

require __DIR__.'/settings.php';
