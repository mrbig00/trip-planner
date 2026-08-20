<?php

use App\Models\Location;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access location create', function () {
    $trip = Trip::factory()->create();

    $response = $this->get(route('locations.create', $trip));
    $response->assertRedirect(route('login'));
});

test('guests cannot access location edit', function () {
    $trip = Trip::factory()->create();
    $location = Location::factory()->create(['trip_id' => $trip->id]);

    $response = $this->get(route('locations.edit', [$trip, $location]));
    $response->assertRedirect(route('login'));
});

test('non-owners cannot access location create', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('locations.create', $trip));
    $response->assertForbidden();
});

test('non-owners cannot access location edit', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('locations.edit', [$trip, $location]));
    $response->assertForbidden();
});

test('participants cannot access location create (creator only)', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    $response = $this->get(route('locations.create', $trip));
    $response->assertForbidden();
});

test('participants cannot access location edit (creator only)', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    $response = $this->get(route('locations.edit', [$trip, $location]));
    $response->assertForbidden();
});

test('mismatched trip and location returns not found on edit', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $locationFromOtherTrip = Location::factory()->create();
    $this->actingAs($owner);

    $response = $this->get(route('locations.edit', [$trip, $locationFromOtherTrip]));
    $response->assertNotFound();
});

test('owner can create a location with all fields', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', 'Paris')
        ->set('price', '150.50')
        ->set('latitude', '48.8566')
        ->set('longitude', '2.3522')
        ->set('link', 'https://example.com')
        ->set('picture', 'https://example.com/paris.jpg')
        ->call('store')
        ->assertRedirect(route('trips.show', $trip))
        ->assertDispatched('analytics-event', name: 'location_created');

    $location = Location::where('name', 'Paris')->first();
    expect($location)->not->toBeNull();
    expect((float) $location->price)->toBe(150.5);
    expect($location->link)->toBe('https://example.com');
});

test('owner can create a location with only required name', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', 'Somewhere')
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $location = Location::where('name', 'Somewhere')->first();
    expect($location)->not->toBeNull();
    expect($location->price)->toBeNull();
});

test('location creation requires a name', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', '')
        ->call('store')
        ->assertHasErrors(['name']);
});

test('location creation rejects a negative price', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', 'Somewhere')
        ->set('price', '-10')
        ->call('store')
        ->assertHasErrors(['price']);
});

test('location creation rejects latitude/longitude out of range', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', 'Somewhere')
        ->set('latitude', '999')
        ->set('longitude', '999')
        ->call('store')
        ->assertHasErrors(['latitude', 'longitude']);
});

test('location creation rejects an invalid link', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('locations.create', ['trip' => $trip])
        ->set('name', 'Somewhere')
        ->set('link', 'not-a-url')
        ->call('store')
        ->assertHasErrors(['link']);
});

test('edit mount pre-fills location fields', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create([
        'trip_id' => $trip->id,
        'name' => 'Rome',
        'price' => 99.99,
        'link' => 'https://example.com/rome',
    ]);
    $this->actingAs($owner);

    Volt::test('locations.edit', ['trip' => $trip, 'location' => $location])
        ->assertSet('name', 'Rome')
        ->assertSet('price', '99.99')
        ->assertSet('link', 'https://example.com/rome');
});

test('owner can update a location', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Old Name']);
    $this->actingAs($owner);

    Volt::test('locations.edit', ['trip' => $trip, 'location' => $location])
        ->set('name', 'New Name')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip))
        ->assertDispatched('analytics-event', name: 'location_updated');

    expect($location->fresh()->name)->toBe('New Name');
});
