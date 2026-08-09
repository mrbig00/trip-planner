<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;
use Livewire\Volt\Volt;

test('no budget bar renders when the trip has no budget set', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'budget' => null]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('Budget');
});

test('the budget bar shows spent against budget when under budget', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'budget' => 100]);
    Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 40.00,
        'quantity' => 1,
    ])->shares()->create(['user_id' => $owner->id, 'amount' => 40.00]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('Budget')
        ->assertSee('$40.00 / $100.00')
        ->assertDontSee('over budget');
});

test('the budget bar flags an over-budget trip instead of silently capping', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'budget' => 100]);
    Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 150.00,
        'quantity' => 1,
    ])->shares()->create(['user_id' => $owner->id, 'amount' => 150.00]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('$50.00 over budget');
});
