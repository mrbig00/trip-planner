<?php

use App\Models\Trip;
use App\Enums\Currency;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

test('backfilling expense currency inherits the parent trip\'s currency and is idempotent', function () {
    $trip = Trip::factory()->create(['currency' => Currency::EUR->value]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'currency' => null,
        'exchange_rate' => null,
    ]);

    DB::table('expenses')->where('id', $expense->id)->update(['currency' => null]);
    expect($expense->fresh()->currency)->toBeNull();

    $migration = require database_path('migrations/2026_08_28_000002_add_currency_and_exchange_rate_to_expenses_table.php');

    $migration->backfillCurrencyForExistingExpenses();

    expect($expense->fresh()->currency)->toBe(Currency::EUR)
        ->and($expense->fresh()->exchange_rate)->toBeNull();

    // Re-running must not touch an expense that already has a currency.
    DB::table('expenses')->where('id', $expense->id)->update(['currency' => Currency::GBP->value]);
    $migration->backfillCurrencyForExistingExpenses();

    expect($expense->fresh()->currency)->toBe(Currency::GBP);
});
