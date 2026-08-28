<?php

use App\Models\Trip;
use App\Enums\Currency;
use App\Models\Expense;

test('a null exchange rate passes an amount through unchanged (implicit rate of 1)', function () {
    $expense = Expense::factory()->create([
        'trip_id' => Trip::factory()->create(['currency' => Currency::USD->value])->id,
        'currency' => Currency::USD->value,
        'exchange_rate' => null,
        'unit_price' => 12.34,
        'quantity' => 1,
    ]);

    expect($expense->convertToTripCurrencyCents('12.34'))->toBe(1234)
        ->and($expense->converted_total_cents)->toBe($expense->total_in_cents);
});

test('a non-null exchange rate converts an amount into the trip\'s currency', function () {
    $expense = Expense::factory()->create([
        'trip_id' => Trip::factory()->create(['currency' => Currency::USD->value])->id,
        'currency' => Currency::EUR->value,
        'exchange_rate' => '1.234567',
        'unit_price' => 10.00,
        'quantity' => 1,
    ]);

    // 10.00 EUR * 1.234567 = 12.34567 -> truncated to 1234 cents, matching
    // the existing truncation behavior of Money::toCents()/total_in_cents.
    expect($expense->convertToTripCurrencyCents('10.00'))->toBe(1234)
        ->and($expense->converted_total_cents)->toBe(1234);
});

test('converted_total_cents reflects unit_price times quantity times the exchange rate', function () {
    $expense = Expense::factory()->create([
        'trip_id' => Trip::factory()->create(['currency' => Currency::USD->value])->id,
        'currency' => Currency::EUR->value,
        'exchange_rate' => '2.0',
        'unit_price' => 5.00,
        'quantity' => 3,
    ]);

    // total = 15.00 EUR * 2.0 = 30.00 USD.
    expect($expense->converted_total_cents)->toBe(3000);
});

test('convertedShareCentsByUserId always sums to exactly converted_total_cents, even when independent per-share truncation would not', function () {
    // 0.10 EUR at a 1.1 rate converts to 0.11 USD (11 cents), but two 0.05
    // shares each independently truncate to 5 cents (10 total, not 11) —
    // this is exactly the drift convertedShareCentsByUserId must avoid.
    $trip = Trip::factory()->create(['currency' => Currency::USD->value]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'currency' => Currency::EUR->value,
        'exchange_rate' => '1.1',
        'unit_price' => 0.10,
        'quantity' => 1,
    ]);
    $userA = \App\Models\User::factory()->create();
    $userB = \App\Models\User::factory()->create();
    $expense->shares()->createMany([
        ['user_id' => $userA->id, 'amount' => 0.05],
        ['user_id' => $userB->id, 'amount' => 0.05],
    ]);

    $shares = $expense->fresh('shares')->convertedShareCentsByUserId();

    expect(array_sum($shares))->toBe($expense->converted_total_cents)
        ->and($expense->converted_total_cents)->toBe(11);
});

test('convertedShareCentsByUserId distributes an uneven remainder deterministically', function () {
    // 1.00 EUR at a 0.333333 rate converts to 0.33 USD (33 cents); two 0.50
    // shares each independently floor-truncate to 16 (32 total, not 33) —
    // the leftover cent must go to exactly one share, not vanish.
    $trip = Trip::factory()->create(['currency' => Currency::USD->value]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'currency' => Currency::EUR->value,
        'exchange_rate' => '0.333333',
        'unit_price' => 1.00,
        'quantity' => 1,
    ]);
    $userA = \App\Models\User::factory()->create();
    $userB = \App\Models\User::factory()->create();
    $expense->shares()->createMany([
        ['user_id' => $userA->id, 'amount' => 0.50],
        ['user_id' => $userB->id, 'amount' => 0.50],
    ]);

    $shares = $expense->fresh('shares')->convertedShareCentsByUserId();

    expect(array_sum($shares))->toBe(33)
        ->and([$shares[$userA->id], $shares[$userB->id]])->toContain(17)
        ->and([$shares[$userA->id], $shares[$userB->id]])->toContain(16);
});
