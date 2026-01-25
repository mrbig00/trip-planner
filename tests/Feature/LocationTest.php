<?php

use App\Models\Location;
use App\Models\Trip;

test('a location can be created', function () {
    $trip = Trip::factory()->create();
    $location = Location::factory()->create([
        'trip_id' => $trip->id,
        'name' => 'Paris',
        'price' => 150.50,
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'accepted' => true,
    ]);

    expect($location->name)->toBe('Paris');
    expect((float) $location->price)->toBe(150.5);
    expect((float) $location->latitude)->toBe(48.8566);
    expect((float) $location->longitude)->toBe(2.3522);
    expect($location->accepted)->toBeTrue();
});

test('a location belongs to a trip', function () {
    $trip = Trip::factory()->create();
    $location = Location::factory()->create(['trip_id' => $trip->id]);

    expect($location->trip)->toBeInstanceOf(Trip::class);
    expect($location->trip->id)->toBe($trip->id);
});

test('a location has nullable fields', function () {
    $trip = Trip::factory()->create();
    $location = Location::factory()->create([
        'trip_id' => $trip->id,
        'price' => null,
        'latitude' => null,
        'longitude' => null,
        'link' => null,
        'picture' => null,
    ]);

    expect($location->price)->toBeNull();
    expect($location->latitude)->toBeNull();
    expect($location->longitude)->toBeNull();
    expect($location->link)->toBeNull();
    expect($location->picture)->toBeNull();
});

test('a location defaults to not accepted', function () {
    $trip = Trip::factory()->create();
    $location = Location::factory()->create([
        'trip_id' => $trip->id,
        'accepted' => false,
    ]);

    expect($location->accepted)->toBeFalse();
});
