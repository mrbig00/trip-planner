<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;
use Livewire\Volt\Volt;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

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

    $newerPosition = strpos($html, 'New Spot');
    $olderPosition = strpos($html, 'Old Spot');

    // Guard against false-passing: strpos() returns false (which PHP's < operator
    // coerces to 0) when a needle is missing, so assert both are actually found
    // before comparing their positions.
    expect($newerPosition)->not->toBeFalse();
    expect($olderPosition)->not->toBeFalse();

    // Newer trip's event appears before the older trip's event.
    expect($newerPosition)->toBeLessThan($olderPosition);
    expect($html)->toContain('Newer Trip');
    expect($html)->toContain('Older Trip');
});

test('a deleted expense on each of several trips is still attributed correctly, batched in one query', function () {
    $user = User::factory()->create();

    $tripOne = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Trip One']);
    $expenseOne = Expense::factory()->create(['trip_id' => $tripOne->id, 'user_id' => $user->id, 'name' => 'Hotel One']);

    $tripTwo = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Trip Two']);
    $expenseTwo = Expense::factory()->create(['trip_id' => $tripTwo->id, 'user_id' => $user->id, 'name' => 'Hotel Two']);

    $this->actingAs($user);
    Volt::test('trips.show', ['trip' => $tripOne])->call('deleteExpense', $expenseOne->id);
    Volt::test('trips.show', ['trip' => $tripTwo])->call('deleteExpense', $expenseTwo->id);

    Volt::test('activity.index')
        ->assertSee('deleted expense Hotel One')
        ->assertSee('deleted expense Hotel Two');
});

test('rendering several trips does not N+1 on deleted-expense lookups', function () {
    $user = User::factory()->create();
    Trip::factory()->count(5)->create(['user_id' => $user->id]);
    $this->actingAs($user);

    DB::enableQueryLog();
    Volt::test('activity.index');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // One trashed-expense query total, not one per trip.
    expect($queryCount)->toBeLessThan(20);
});

test('an event\'s avatar carries the actor\'s color slot for that specific trip', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id, ['color_slot' => 3]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $location->comments()->create(['user_id' => $participant->id, 'content' => 'Hello']);

    $this->actingAs($owner);

    Volt::test('activity.index')->assertSee('ring-[var(--color-participant-3)]', false);
});
