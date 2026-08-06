<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('a trip with no expenses shows the empty state', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('No expenses added yet.');
});

test('a trip where the payer is the only sharer is settled up', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => 20.00]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('Everyone is settled up!');
});

test('a genuine imbalance shows the correct balances and suggested transfer', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 40.00,
        'quantity' => 1,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 20.00],
        ['user_id' => $participant->id, 'amount' => 20.00],
    ]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('is owed')
        ->assertSee('owes')
        ->assertSee('$20.00');
});

test('a non-creator participant can also see the settle up card', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 40.00,
        'quantity' => 1,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 20.00],
        ['user_id' => $participant->id, 'amount' => 20.00],
    ]);
    $this->actingAs($participant);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('Settle Up')
        ->assertSee('is owed');
});
