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
