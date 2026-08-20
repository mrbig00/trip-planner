<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;
use Livewire\Volt\Volt;
use App\Models\Settlement;

function tripWithImbalance(): array
{
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

    return [$trip, $owner, $participant];
}

test('the trip creator can mark a suggested transfer as settled', function () {
    [$trip, $owner, $participant] = tripWithImbalance();
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('markTransferSettled', $participant->id, $owner->id, 2000)
        ->assertSee('Everyone is settled up!')
        ->assertSee('Settled')
        ->assertDispatched('analytics-event', name: 'settlement_recorded');

    expect(Settlement::query()->count())->toBe(1);
    $settlement = Settlement::first();
    expect($settlement->from_user_id)->toBe($participant->id)
        ->and($settlement->to_user_id)->toBe($owner->id)
        ->and($settlement->amount_cents)->toBe(2000)
        ->and($settlement->recorded_by_user_id)->toBe($owner->id);
});

test('either party to the transfer can mark it as settled', function () {
    [$trip, $owner, $participant] = tripWithImbalance();
    $this->actingAs($participant);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('markTransferSettled', $participant->id, $owner->id, 2000)
        ->assertSee('Everyone is settled up!');

    expect(Settlement::query()->count())->toBe(1);
});

test('an unrelated participant cannot mark a transfer between two other members as settled', function () {
    [$trip, $owner, $participant] = tripWithImbalance();
    $unrelated = User::factory()->create();
    $trip->participants()->attach($unrelated->id);
    $this->actingAs($unrelated);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('markTransferSettled', $participant->id, $owner->id, 2000)
        ->assertForbidden();

    expect(Settlement::query()->count())->toBe(0);
});

test('a settlement amount exceeding what is actually owed is rejected', function () {
    [$trip, $owner, $participant] = tripWithImbalance();
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('markTransferSettled', $participant->id, $owner->id, 999_999)
        ->assertStatus(422);

    expect(Settlement::query()->count())->toBe(0);
});

test('a settlement persists across a fresh page load and updates the balances shown', function () {
    [$trip, $owner, $participant] = tripWithImbalance();
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('markTransferSettled', $participant->id, $owner->id, 2000);

    Volt::test('trips.show', ['trip' => $trip->fresh()])
        ->assertSee('Everyone is settled up!')
        ->assertDontSee('owes')
        ->assertDontSee('is owed');
});
