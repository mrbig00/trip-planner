<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;
use App\Models\Location;

test('no combined map widget renders when no location has coordinates', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'latitude' => null, 'longitude' => null]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('tile.openstreetmap.org', false);
});

test('a single located location renders the widget with a pin but no connecting line', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'latitude' => 48.8584, 'longitude' => 2.2945]);
    $this->actingAs($owner);

    $html = Volt::test('trips.show', ['trip' => $trip])->html();

    expect($html)->toContain('tile.openstreetmap.org')
        ->not->toContain('<polyline');
});

test('multiple located locations render the widget with a connecting polyline', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Eiffel Tower', 'latitude' => 48.8584, 'longitude' => 2.2945]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Arc de Triomphe', 'latitude' => 48.8738, 'longitude' => 2.2950]);
    $this->actingAs($owner);

    $html = Volt::test('trips.show', ['trip' => $trip])->html();

    expect($html)->toContain('tile.openstreetmap.org')
        ->toContain('<polyline');
});
