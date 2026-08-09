<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;

test('budget summary is null when no budget is set', function () {
    $trip = Trip::factory()->create(['budget' => null]);

    expect($trip->load('expenses')->budget_summary)->toBeNull();
});

test('a zero budget with nothing spent reads as fully used, not empty', function () {
    $trip = Trip::factory()->create(['budget' => 0]);

    $summary = $trip->load('expenses')->budget_summary;

    expect($summary['percentUsed'])->toBe(100.0);
    expect($summary['overBudget'])->toBeFalse();
});

test('a zero budget with any spend is flagged over budget', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'budget' => 0]);
    Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'unit_price' => 10, 'quantity' => 1]);

    $summary = $trip->load('expenses')->budget_summary;

    expect($summary['percentUsed'])->toBe(100.0);
    expect($summary['overBudget'])->toBeTrue();
});

test('percentRaw stays uncapped so ranking can distinguish degrees of over-budget', function () {
    $owner = User::factory()->create();

    $slightlyOver = Trip::factory()->create(['user_id' => $owner->id, 'budget' => 100]);
    Expense::factory()->create(['trip_id' => $slightlyOver->id, 'user_id' => $owner->id, 'unit_price' => 110, 'quantity' => 1]);

    $wayOver = Trip::factory()->create(['user_id' => $owner->id, 'budget' => 100]);
    Expense::factory()->create(['trip_id' => $wayOver->id, 'user_id' => $owner->id, 'unit_price' => 400, 'quantity' => 1]);

    $slightlyOverSummary = $slightlyOver->load('expenses')->budget_summary;
    $wayOverSummary = $wayOver->load('expenses')->budget_summary;

    // Both display-capped the same way...
    expect($slightlyOverSummary['percentUsed'])->toBe(100.0);
    expect($wayOverSummary['percentUsed'])->toBe(100.0);

    // ...but the uncapped figure still tells them apart for ranking.
    expect($wayOverSummary['percentRaw'])->toBeGreaterThan($slightlyOverSummary['percentRaw']);
});
