<?php

use App\Models\Expense;
use App\Models\Trip;

test('an expense can be created', function () {
    $trip = Trip::factory()->create();
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'name' => 'Hotel Room',
        'unit_price' => 100.00,
        'quantity' => 3,
    ]);

    expect($expense->name)->toBe('Hotel Room');
    expect((float) $expense->unit_price)->toBe(100.0);
    expect($expense->quantity)->toBe(3);
});

test('an expense belongs to a trip', function () {
    $trip = Trip::factory()->create();
    $expense = Expense::factory()->create(['trip_id' => $trip->id]);

    expect($expense->trip)->toBeInstanceOf(Trip::class);
    expect($expense->trip->id)->toBe($trip->id);
});

test('an expense calculates total correctly', function () {
    $trip = Trip::factory()->create();
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'unit_price' => 50.00,
        'quantity' => 4,
    ]);

    expect((float) $expense->total)->toBe(200.0);
});

test('an expense defaults to quantity of 1', function () {
    $trip = Trip::factory()->create();
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'quantity' => 1,
    ]);

    expect($expense->quantity)->toBe(1);
});
