<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Expense;
use Livewire\Volt\Volt;
use App\Models\Location;

test('no activity card renders for a trip with no events yet', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('Recent Activity');
});

test('recent activity merges comments, votes, acceptance, expenses, and settlements newest-first', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Eiffel Tower']);

    // created_at isn't mass-assignable on LocationComment, so set it directly
    // (a plain property assignment, not mass assignment) to control ordering.
    $comment = $location->comments()->create(['user_id' => $owner->id, 'content' => 'Great pick!']);
    $comment->created_at = now()->subMinutes(50);
    $comment->save();
    $location->votes()->attach($participant->id, ['created_at' => now()->subMinutes(40), 'updated_at' => now()->subMinutes(40)]);
    $location->update(['accepted' => true, 'accepted_at' => now()->subMinutes(30)]);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'created_at' => now()->subMinutes(20),
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => $expense->total]);

    $trip->settlements()->create([
        'from_user_id' => $participant->id,
        'to_user_id' => $owner->id,
        'amount_cents' => 500,
        'created_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($owner);
    $activity = Volt::test('trips.show', ['trip' => $trip])->get('recentActivity');

    expect($activity)->toHaveCount(5);
    expect($activity->pluck('type')->all())->toBe(['settlement', 'expense', 'accepted', 'vote', 'comment']);
    expect($activity[2]['user'])->toBeNull(); // "accepted" has no attributable actor
    expect($activity[4]['text'])->toContain('commented on');
});

test('recent activity is capped at 5 events', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);

    foreach (range(1, 7) as $i) {
        $location->comments()->create(['user_id' => $owner->id, 'content' => "Comment {$i}", 'created_at' => now()->subMinutes($i)]);
    }

    $this->actingAs($owner);
    $activity = Volt::test('trips.show', ['trip' => $trip])->get('recentActivity');

    expect($activity)->toHaveCount(5);
});
