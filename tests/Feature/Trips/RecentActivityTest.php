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

test('recent activity shows the amount on an added expense and adds an edited event once one is saved', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'name' => 'Hotel',
        'unit_price' => 100,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => $expense->total]);

    $this->actingAs($owner);
    $component = Volt::test('trips.show', ['trip' => $trip]);

    $added = $component->get('recentActivity')->firstWhere('type', 'expense');
    expect($added['text'])->toContain('Hotel')->toContain('100.00');

    $component->set('editingExpense', [
        'name' => 'Hotel', 'description' => '', 'link' => '',
        'unit_price' => '150', 'quantity' => 1, 'user_id' => $owner->id,
        'split_type' => 'equal', 'currency' => $trip->currency->value, 'exchange_rate' => null,
        'participant_ids' => [$owner->id],
        'percentages' => [], 'fixed_amounts' => [],
    ])->call('saveExpense', $expense->id);

    $activity = $component->get('recentActivity');
    $edited = $activity->firstWhere('type', 'expense_edited');

    expect($edited)->not->toBeNull();
    expect($edited['user']->id)->toBe($owner->id);
    expect($edited['text'])->toContain('Hotel')->toContain('150.00');
});

test('recent activity attributes an expense added on behalf of someone else to its submitter', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $submitter = User::factory()->create();
    $beneficiary = User::factory()->create();
    $trip->participants()->attach([$submitter->id, $beneficiary->id]);
    Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $beneficiary->id,
        'created_by' => $submitter->id,
        'name' => 'Taxi',
        'unit_price' => 15,
        'quantity' => 1,
    ]);

    $this->actingAs($owner);
    $added = Volt::test('trips.show', ['trip' => $trip])->get('recentActivity')->firstWhere('type', 'expense');

    expect($added['user']->id)->toBe($submitter->id)
        ->and($added['text'])->toContain($submitter->fullName())
        ->toContain($beneficiary->fullName())
        ->toContain('Taxi');
});

test('recent activity still attributes an expense to its submitter once the owner account is deleted', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $submitter = User::factory()->create();
    $beneficiary = User::factory()->create();
    $trip->participants()->attach([$submitter->id, $beneficiary->id]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $beneficiary->id,
        'created_by' => $submitter->id,
        'name' => 'Taxi',
        'unit_price' => 15,
        'quantity' => 1,
    ]);
    $beneficiary->delete();

    $this->actingAs($owner);
    $added = Volt::test('trips.show', ['trip' => $trip])->get('recentActivity')->firstWhere('type', 'expense');

    expect($expense->fresh()->user_id)->toBeNull()
        ->and($added['user']->id)->toBe($submitter->id)
        ->and($added['text'])->toContain($submitter->fullName())
        ->toContain('Taxi');
});

test('recent activity shows a deleted-expense event attributed to whoever deleted it', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $participant->id,
        'name' => 'Rental Car',
        'unit_price' => 200,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $participant->id, 'amount' => $expense->total]);

    // The trip creator (not the expense's owner) is the one who deletes it.
    $this->actingAs($owner);
    $component = Volt::test('trips.show', ['trip' => $trip])->call('deleteExpense', $expense->id);

    $activity = $component->get('recentActivity');
    $deleted = $activity->firstWhere('type', 'expense_deleted');

    expect($deleted)->not->toBeNull();
    expect($deleted['user']->id)->toBe($owner->id);
    expect($deleted['text'])->toContain('Rental Car')->toContain('200.00');
    expect($activity->firstWhere('type', 'expense'))->toBeNull(); // deleted expenses don't also show an "added" event
});
