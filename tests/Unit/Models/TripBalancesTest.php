<?php

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use App\Models\Expense;
use App\Enums\ExpenseSplitType;

function tripWithLoadedExpenses(Trip $trip): Trip
{
    return $trip->fresh(['creator', 'participants', 'expenses.shares', 'settlements']);
}

test('a single equally split expense produces opposite balances for payer and sharer', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Equal->value,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(1000)
        ->and($balances->get($participant->id))->toBe(-1000);
});

test('a member excluded from an expense share is unaffected by it', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => 20.00]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(0)
        ->and($balances->get($participant->id))->toBe(0);
});

test('mixed split types across multiple expenses keep the zero-sum invariant', function () {
    $owner = User::factory()->create();
    $participantA = User::factory()->create();
    $participantB = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach([$participantA->id, $participantB->id]);

    $equalExpense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 30.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Equal->value,
    ]);
    $equalExpense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participantA->id, 'amount' => 10.00],
        ['user_id' => $participantB->id, 'amount' => 10.00],
    ]);

    $percentageExpense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $participantA->id,
        'unit_price' => 100.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Percentage->value,
    ]);
    $percentageExpense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 25.00, 'percentage' => 25],
        ['user_id' => $participantB->id, 'amount' => 75.00, 'percentage' => 75],
    ]);

    $fixedExpense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $participantB->id,
        'unit_price' => 40.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Fixed->value,
    ]);
    $fixedExpense->shares()->createMany([
        ['user_id' => $participantA->id, 'amount' => 15.00],
        ['user_id' => $participantB->id, 'amount' => 25.00],
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    // owner: +3000 (paid eq) -1000 (owes eq) -2500 (owes pct) = -500
    // A:     -1000 (owes eq) +10000 (paid pct) -1500 (owes fixed) = 7500
    // B:     -1000 (owes eq) -7500 (owes pct) +4000 (paid fixed) -2500 (owes fixed) = -7000
    expect($balances->sum())->toBe(0)
        ->and($balances->get($owner->id))->toBe(-500)
        ->and($balances->get($participantA->id))->toBe(7500)
        ->and($balances->get($participantB->id))->toBe(-7000);
});

test('a recorded settlement nets out of both parties balances', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $trip->settlements()->create([
        'from_user_id' => $participant->id,
        'to_user_id' => $owner->id,
        'amount_cents' => 1000,
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(0)
        ->and($balances->get($participant->id))->toBe(0);
});

test('an expense in a different currency is converted to the trip\'s currency before netting', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $trip->participants()->attach($participant->id);

    // 20.00 EUR at a 1.1 rate to USD = 22.00 USD (2200 cents).
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
        'currency' => Currency::EUR->value,
        'exchange_rate' => '1.1',
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(1100)
        ->and($balances->get($participant->id))->toBe(-1100);
});

test('an expense in the trip\'s own currency (null exchange rate) nets at face value', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
        'currency' => Currency::USD->value,
        'exchange_rate' => null,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(1000)
        ->and($balances->get($participant->id))->toBe(-1000);
});

test('a partial settlement leaves the remaining balance', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $trip->settlements()->create([
        'from_user_id' => $participant->id,
        'to_user_id' => $owner->id,
        'amount_cents' => 400,
    ]);

    $balances = tripWithLoadedExpenses($trip)->balances();

    expect($balances->get($owner->id))->toBe(600)
        ->and($balances->get($participant->id))->toBe(-600);
});
