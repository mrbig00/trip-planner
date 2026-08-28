<?php

use App\Models\Trip;
use App\Enums\Currency;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

test('backfilling location currency inherits the parent trip\'s currency and is idempotent', function () {
    $trip = Trip::factory()->create(['currency' => Currency::EUR->value]);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'currency' => null]);

    DB::table('locations')->where('id', $location->id)->update(['currency' => null]);
    expect($location->fresh()->currency)->toBeNull();

    $migration = require database_path('migrations/2026_08_28_000003_add_currency_to_locations_table.php');

    $migration->backfillCurrencyForExistingLocations();

    expect($location->fresh()->currency)->toBe(Currency::EUR);

    // Re-running must not touch a location that already has a currency.
    DB::table('locations')->where('id', $location->id)->update(['currency' => Currency::GBP->value]);
    $migration->backfillCurrencyForExistingLocations();

    expect($location->fresh()->currency)->toBe(Currency::GBP);
});
