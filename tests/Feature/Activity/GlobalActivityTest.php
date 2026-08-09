<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;
use App\Models\Location;

test('guests cannot access the activity page', function () {
    $response = $this->get(route('activity.index'));
    $response->assertRedirect(route('login'));
});

test('an empty state shows when nothing has happened yet', function () {
    $user = User::factory()->create();
    Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('activity.index')->assertSee("Nothing's happened yet");
});

test('events from a trip the user does not share do not appear', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $stranger->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Secret Spot']);
    $location->comments()->create(['user_id' => $stranger->id, 'content' => 'Nice find']);
    $this->actingAs($user);

    Volt::test('activity.index')->assertDontSee('Secret Spot');
});

test('events from multiple trips are merged newest-first and each links to its own trip', function () {
    $user = User::factory()->create();

    $olderTrip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Older Trip']);
    $olderLocation = Location::factory()->create(['trip_id' => $olderTrip->id, 'name' => 'Old Spot']);
    $olderComment = $olderLocation->comments()->create(['user_id' => $user->id, 'content' => 'First']);
    $olderComment->created_at = now()->subDays(2);
    $olderComment->save();

    $newerTrip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Newer Trip']);
    $newerLocation = Location::factory()->create(['trip_id' => $newerTrip->id, 'name' => 'New Spot']);
    $newerComment = $newerLocation->comments()->create(['user_id' => $user->id, 'content' => 'Second']);
    $newerComment->created_at = now()->subHour();
    $newerComment->save();

    $this->actingAs($user);

    $html = Volt::test('activity.index')->html();

    // Newer trip's event appears before the older trip's event.
    expect(strpos($html, 'New Spot'))->toBeLessThan(strpos($html, 'Old Spot'));
    expect($html)->toContain('Newer Trip');
    expect($html)->toContain('Older Trip');
});
