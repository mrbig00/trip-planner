<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('equal split among a subset of members divides the total and gives remainder cents to the lowest user ids', function () {
    $owner = User::factory()->create();
    $participantA = User::factory()->create();
    $participantB = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach([$participantA->id, $participantB->id]);
    $this->actingAs($owner);

    // $10.00 split between the two lowest-id members of the selected trio.
    $selected = collect([$owner->id, $participantA->id, $participantB->id])->sort()->values();

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Snacks')
        ->set('unit_price', '10.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'equal')
        ->set('participant_ids', [$selected[0], $selected[1]])
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $expense = Expense::where('name', 'Snacks')->firstOrFail();
    $shares = $expense->shares()->orderBy('user_id')->get();

    expect($shares)->toHaveCount(2)
        ->and((float) $shares->sum('amount'))->toBe(10.00)
        ->and((float) $shares->first()->amount)->toBe(5.00)
        ->and((float) $shares->last()->amount)->toBe(5.00);
});

test('percentage split summing to exactly 100 stores the exact entered percentages', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Hotel')
        ->set('unit_price', '200.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'percentage')
        ->set('participant_ids', [$owner->id, $participant->id])
        ->set('percentages', [$owner->id => '25', $participant->id => '75'])
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $expense = Expense::where('name', 'Hotel')->firstOrFail();

    expect((float) $expense->shares()->where('user_id', $owner->id)->first()->amount)->toBe(50.00)
        ->and((float) $expense->shares()->where('user_id', $participant->id)->first()->amount)->toBe(150.00);
});

test('percentage split within tolerance still produces amounts that sum exactly to the total', function () {
    $owner = User::factory()->create();
    $participantA = User::factory()->create();
    $participantB = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach([$participantA->id, $participantB->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '100.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'percentage')
        ->set('participant_ids', [$owner->id, $participantA->id, $participantB->id])
        ->set('percentages', [$owner->id => '33.33', $participantA->id => '33.33', $participantB->id => '33.33'])
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $expense = Expense::where('name', 'Dinner')->firstOrFail();

    expect((float) $expense->shares()->sum('amount'))->toBe(100.00);
});

test('percentage split not summing near 100 fails validation', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Taxi')
        ->set('unit_price', '50.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'percentage')
        ->set('participant_ids', [$owner->id, $participant->id])
        ->set('percentages', [$owner->id => '50', $participant->id => '30'])
        ->call('store')
        ->assertHasErrors(['percentages']);

    expect(Expense::where('name', 'Taxi')->exists())->toBeFalse();
});

test('fixed split summing exactly to the total stores the entered amounts', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Groceries')
        ->set('unit_price', '30.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'fixed')
        ->set('participant_ids', [$owner->id, $participant->id])
        ->set('fixed_amounts', [$owner->id => '20.00', $participant->id => '10.00'])
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $expense = Expense::where('name', 'Groceries')->firstOrFail();

    expect((float) $expense->shares()->where('user_id', $owner->id)->first()->amount)->toBe(20.00)
        ->and((float) $expense->shares()->where('user_id', $participant->id)->first()->amount)->toBe(10.00);
});

test('fixed split off by a cent fails validation', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Groceries')
        ->set('unit_price', '30.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('split_type', 'fixed')
        ->set('participant_ids', [$owner->id, $participant->id])
        ->set('fixed_amounts', [$owner->id => '20.00', $participant->id => '10.01'])
        ->call('store')
        ->assertHasErrors(['fixed_amounts']);

    expect(Expense::where('name', 'Groceries')->exists())->toBeFalse();
});

test('deselecting every participant fails validation', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Snacks')
        ->set('unit_price', '10.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('participant_ids', [])
        ->call('store')
        ->assertHasErrors(['participant_ids']);
});

test('an ineligible participant id fails validation', function () {
    $owner = User::factory()->create();
    $unaffiliated = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Snacks')
        ->set('unit_price', '10.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('participant_ids', [$owner->id, $unaffiliated->id])
        ->call('store')
        ->assertHasErrors(['participant_ids.1']);
});

test('duplicate participant ids fail validation instead of hitting the database', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Snacks')
        ->set('unit_price', '10.00')
        ->set('quantity', 1)
        ->set('user_id', $owner->id)
        ->set('participant_ids', [$owner->id, $owner->id])
        ->call('store')
        ->assertHasErrors(['participant_ids.0', 'participant_ids.1']);

    expect(Expense::where('name', 'Snacks')->exists())->toBeFalse();
});
