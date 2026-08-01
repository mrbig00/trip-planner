<?php

use App\Models\Location;
use App\Models\Trip;
use App\Models\User;

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

test('accepting a location unaccepts its siblings in the same trip', function () {
    $trip = Trip::factory()->create();
    $accepted = Location::factory()->create(['trip_id' => $trip->id, 'accepted' => true]);
    $other = Location::factory()->create(['trip_id' => $trip->id, 'accepted' => false]);

    $other->accept();

    expect($other->fresh()->accepted)->toBeTrue();
    expect($accepted->fresh()->accepted)->toBeFalse();
});

test('accepting a location does not affect locations in other trips', function () {
    $tripA = Trip::factory()->create();
    $tripB = Trip::factory()->create();
    $locationA = Location::factory()->create(['trip_id' => $tripA->id, 'accepted' => false]);
    $locationB = Location::factory()->create(['trip_id' => $tripB->id, 'accepted' => true]);

    $locationA->accept();

    expect($locationA->fresh()->accepted)->toBeTrue();
    expect($locationB->fresh()->accepted)->toBeTrue();
});

test('toggleVote attaches a vote on first call and detaches on second', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create();

    $attached = $location->toggleVote($user);
    expect($attached)->toBeTrue();
    expect($location->hasVoteFrom($user))->toBeTrue();

    $detached = $location->toggleVote($user);
    expect($detached)->toBeFalse();
    expect($location->hasVoteFrom($user))->toBeFalse();
});

test('hasVoteFrom reports whether the given user has voted', function () {
    $location = Location::factory()->create();
    $voter = User::factory()->create();
    $nonVoter = User::factory()->create();

    $location->votes()->attach($voter->id);

    expect($location->hasVoteFrom($voter))->toBeTrue();
    expect($location->hasVoteFrom($nonVoter))->toBeFalse();
});

test('votes returns the users who voted for the location', function () {
    $location = Location::factory()->create();
    $voter = User::factory()->create();

    $location->votes()->attach($voter->id);

    expect($location->votes()->pluck('users.id'))->toContain($voter->id);
});
