<?php

use App\Models\Trip;
use App\Models\Expense;
use Database\Seeders\TripSeeder;
use Database\Seeders\UserSeeder;

test('every seeded trip balances to exactly zero', function () {
    $this->seed(UserSeeder::class);
    $this->seed(TripSeeder::class);

    $trips = Trip::with(['creator', 'participants', 'expenses.shares'])->get();

    expect($trips)->not->toBeEmpty();

    foreach ($trips as $trip) {
        $sum = $trip->balances()->sum();

        expect($sum)->toBe(0, "Trip #{$trip->id} ({$trip->name}) balances sum to {$sum} cents instead of 0 — some expense is missing shares.");
    }
});

test('every seeded expense has at least one share', function () {
    $this->seed(UserSeeder::class);
    $this->seed(TripSeeder::class);

    $orphaned = Expense::doesntHave('shares')->count();

    expect($orphaned)->toBe(0);
});
