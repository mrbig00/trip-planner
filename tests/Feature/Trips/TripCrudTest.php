<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access trips', function () {
    $response = $this->get(route('trips.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view their trips', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $myTrip = Trip::factory()->create(['user_id' => $user->id]);
    $otherTrip = Trip::factory()->create();

    $response = $this->get(route('trips.index'));
    $response->assertSuccessful();
    $response->assertSee($myTrip->name);
});

test('users can create a trip', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Summer Vacation')
        ->set('description', 'A fun summer trip')
        ->call('store')
        ->assertRedirect(route('trips.show', Trip::where('name', 'Summer Vacation')->first()));

    expect(Trip::where('name', 'Summer Vacation')->exists())->toBeTrue();
});

test('trip creation requires name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', '')
        ->set('description', 'A fun summer trip')
        ->call('store')
        ->assertHasErrors(['name']);
});

test('users can view trip details', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('trips.show', $trip));
    $response->assertSuccessful();
    $response->assertSee($trip->name);
});

test('users can edit their own trips', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('name', 'Updated Trip Name')
        ->set('description', 'Updated description')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh()->name)->toBe('Updated Trip Name');
    expect($trip->fresh()->description)->toBe('Updated description');
});

test('users cannot edit other users trips', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->get(route('trips.edit', $trip));
    $response->assertForbidden();
});

test('users can delete their own trips', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('delete')
        ->assertRedirect(route('trips.index'));

    expect(Trip::find($trip->id))->toBeNull();
});

test('users cannot delete other users trips', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = Volt::test('trips.show', ['trip' => $trip])
        ->call('delete');

    expect(Trip::find($trip->id))->not->toBeNull();
});

test('users can see trips they participate in', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id]);
    $trip->participants()->attach($user->id);
    $this->actingAs($user);

    $response = $this->get(route('trips.index'));
    $response->assertSuccessful();
    $response->assertSee($trip->name);
});

test('trip description is optional', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Trip Without Description')
        ->set('description', '')
        ->call('store')
        ->assertRedirect(route('trips.show', Trip::where('name', 'Trip Without Description')->first()));

    $trip = Trip::where('name', 'Trip Without Description')->first();
    expect($trip->description)->toBeNull();
});
