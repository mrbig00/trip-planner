<?php

use App\Models\Trip;
use App\Enums\Currency;
use Illuminate\Support\Facades\DB;

test('backfilling trip currency is correct and idempotent on a real double-run', function () {
    $trip = Trip::factory()->create(['currency' => Currency::EUR->value]);

    DB::table('trips')->where('id', $trip->id)->update(['currency' => null]);
    expect($trip->fresh()->currency)->toBeNull();

    $migration = require database_path('migrations/2026_08_28_000001_add_currency_to_trips_table.php');

    $migration->backfillCurrencyForExistingTrips();

    expect($trip->fresh()->currency)->toBe(Currency::default());

    // Re-running must not touch a trip that already has a currency.
    DB::table('trips')->where('id', $trip->id)->update(['currency' => Currency::GBP->value]);
    $migration->backfillCurrencyForExistingTrips();

    expect($trip->fresh()->currency)->toBe(Currency::GBP);
});
