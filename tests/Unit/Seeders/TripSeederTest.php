<?php

use App\Models\Trip;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\TripSeeder;

test('every seeded trip balances to exactly zero', function () {
    $this->seed(UserSeeder::class);
    $this->seed(TripSeeder::class);

    $trips = Trip::with(['creator', 'participants', 'expenses.shares'])->get();

    expect($trips)->not->toBeEmpty();

    foreach ($trips as $trip) {
        expect($trip->balances()->sum())
            ->toBe(0, "Trip #{$trip->id} ({$trip->name}) balances sum to {$trip->balances()->sum()} cents instead of 0 — some expense is missing shares.");
    }
});

test('every seeded expense has at least one share', function () {
    $this->seed(UserSeeder::class);
    $this->seed(TripSeeder::class);

    $orphaned = \App\Models\Expense::doesntHave('shares')->count();

    expect($orphaned)->toBe(0);
});
