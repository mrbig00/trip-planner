<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('trips search filters by partial name match', function () {
    $user = User::factory()->create();
    $matching = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Summer in Paris']);
    $nonMatching = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Winter in Oslo']);
    $this->actingAs($user);

    $component = Volt::test('trips.index')
        ->set('search', 'Paris');

    $trips = $component->instance()->trips();

    expect($trips->pluck('id'))->toContain($matching->id);
    expect($trips->pluck('id'))->not->toContain($nonMatching->id);
});

test('owner can delete a trip from the index', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('trips.index')->call('delete', $trip);

    expect(Trip::find($trip->id))->toBeNull();
});

test('non-owner cannot delete a trip from the index', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.index')
        ->call('delete', $trip)
        ->assertForbidden();

    expect(Trip::find($trip->id))->not->toBeNull();
});
