<?php

use App\Models\Expense;
use App\Models\Location;
use App\Models\Trip;
use App\Models\User;

test('a trip can be created', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);

    expect($trip->name)->toBeString();
    expect($trip->description)->toBeString();
    expect($trip->user_id)->toBe($user->id);
});

test('a trip belongs to a creator', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);

    expect($trip->creator)->toBeInstanceOf(User::class);
    expect($trip->creator->id)->toBe($user->id);
});

test('a trip can have multiple locations', function () {
    $trip = Trip::factory()->create();
    $locations = Location::factory()->count(3)->create(['trip_id' => $trip->id]);

    expect($trip->locations)->toHaveCount(3);
    expect($trip->locations->first())->toBeInstanceOf(Location::class);
});

test('a trip can have multiple expenses', function () {
    $trip = Trip::factory()->create();
    $expenses = Expense::factory()->count(3)->create(['trip_id' => $trip->id]);

    expect($trip->expenses)->toHaveCount(3);
    expect($trip->expenses->first())->toBeInstanceOf(Expense::class);
});

test('a trip can have multiple participants', function () {
    $creator = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id]);
    $participants = User::factory()->count(3)->create();

    $trip->participants()->attach($participants->pluck('id'));

    expect($trip->participants)->toHaveCount(3);
    expect($trip->participants->first())->toBeInstanceOf(User::class);
});

test('a user can create multiple trips', function () {
    $user = User::factory()->create();
    $trips = Trip::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->createdTrips)->toHaveCount(3);
    expect($user->createdTrips->first())->toBeInstanceOf(Trip::class);
});

test('a user can participate in multiple trips', function () {
    $user = User::factory()->create();
    $trips = Trip::factory()->count(3)->create();

    $trips->each(fn ($trip) => $trip->participants()->attach($user->id));

    expect($user->trips)->toHaveCount(3);
    expect($user->trips->first())->toBeInstanceOf(Trip::class);
});
