<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;
use App\Models\Location;

test('guests cannot access the explore page', function () {
    $response = $this->get(route('explore.index'));
    $response->assertRedirect(route('login'));
});

test('an empty state shows when no location has coordinates', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'latitude' => null, 'longitude' => null]);
    $this->actingAs($user);

    Volt::test('explore.index')->assertDontSee('tile.openstreetmap.org', false);
});

test('a single located location renders the map with a pin', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Eiffel Tower', 'latitude' => 48.8584, 'longitude' => 2.2945]);
    $this->actingAs($user);

    $html = Volt::test('explore.index')->html();

    expect($html)->toContain('tile.openstreetmap.org')
        ->toContain('Eiffel Tower');
});

test('a location at latitude/longitude 0.0 still renders as a pin', function () {
    // 0.0 is falsy in PHP — the map widget must use a null check, not a
    // truthy check, to decide whether a location has coordinates.
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Null Island', 'latitude' => 0.0, 'longitude' => 0.0]);
    $this->actingAs($user);

    $html = Volt::test('explore.index')->html();

    expect($html)->toContain('tile.openstreetmap.org')
        ->toContain('Null Island');
});

test('a location from a trip the user does not share does not appear as a pin', function () {
    $user = User::factory()->create();
    $ownTrip = Trip::factory()->create(['user_id' => $user->id]);
    Location::factory()->create(['trip_id' => $ownTrip->id, 'name' => 'My Spot', 'latitude' => 48.8584, 'longitude' => 2.2945]);

    $stranger = User::factory()->create();
    $strangerTrip = Trip::factory()->create(['user_id' => $stranger->id]);
    Location::factory()->create(['trip_id' => $strangerTrip->id, 'name' => 'Secret Spot', 'latitude' => 51.5074, 'longitude' => -0.1278]);

    $this->actingAs($user);

    $html = Volt::test('explore.index')->html();

    expect($html)->toContain('My Spot')
        ->not->toContain('Secret Spot');
});

test('locations from multiple trips each link to their own trip', function () {
    $user = User::factory()->create();

    $tripOne = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Paris Trip']);
    $locationOne = Location::factory()->create(['trip_id' => $tripOne->id, 'name' => 'Eiffel Tower', 'latitude' => 48.8584, 'longitude' => 2.2945]);

    $tripTwo = Trip::factory()->create(['user_id' => $user->id, 'name' => 'London Trip']);
    $locationTwo = Location::factory()->create(['trip_id' => $tripTwo->id, 'name' => 'Big Ben', 'latitude' => 51.5007, 'longitude' => -0.1246]);

    $this->actingAs($user);

    $html = Volt::test('explore.index')->html();

    expect($html)->toContain(route('trips.show', $tripOne))
        ->toContain(route('trips.show', $tripTwo))
        ->toContain('Eiffel Tower — Paris Trip')
        ->toContain('Big Ben — London Trip');
});

test('a location a user participates in (but did not create the trip for) still appears', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id]);
    $trip->participants()->attach($participant->id);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Shared Spot', 'latitude' => 40.7128, 'longitude' => -74.0060]);
    $this->actingAs($participant);

    Volt::test('explore.index')->assertSee('Shared Spot');
});
