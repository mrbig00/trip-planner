<?php

use App\Models\Trip;

test('no countdown label when neither date is set', function () {
    $trip = Trip::factory()->create(['start_date' => null, 'end_date' => null]);

    expect($trip->countdownLabel())->toBeNull();
});

test('an upcoming trip shows days to go', function () {
    $trip = Trip::factory()->create([
        'start_date' => today()->addDays(5),
        'end_date' => today()->addDays(10),
    ]);

    expect($trip->countdownLabel())->toBe('5 days to go');
});

test('a trip starting tomorrow uses the singular "day"', function () {
    $trip = Trip::factory()->create([
        'start_date' => today()->addDay(),
        'end_date' => null,
    ]);

    expect($trip->countdownLabel())->toBe('1 day to go');
});

test('a trip starting today reads "Starting today"', function () {
    $trip = Trip::factory()->create([
        'start_date' => today(),
        'end_date' => today()->addDays(3),
    ]);

    expect($trip->countdownLabel())->toBe('Starting today');
});

test('a trip between its start and end dates reads "Happening now"', function () {
    $trip = Trip::factory()->create([
        'start_date' => today()->subDays(2),
        'end_date' => today()->addDays(2),
    ]);

    expect($trip->countdownLabel())->toBe('Happening now');
});

test('a trip past its start date with no end date reads "Happening now"', function () {
    $trip = Trip::factory()->create([
        'start_date' => today()->subDays(30),
        'end_date' => null,
    ]);

    expect($trip->countdownLabel())->toBe('Happening now');
});

test('a trip past its end date reads "Trip ended"', function () {
    $trip = Trip::factory()->create([
        'start_date' => today()->subDays(10),
        'end_date' => today()->subDay(),
    ]);

    expect($trip->countdownLabel())->toBe('Trip ended');
});
