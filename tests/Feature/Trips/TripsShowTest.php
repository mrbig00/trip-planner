<?php

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use App\Models\Expense;
use Livewire\Volt\Volt;
use App\Models\Location;
use App\Models\LocationComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('trip creator can accept a location', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'accepted' => false]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('acceptLocation', $location->id)
        ->assertDispatched('analytics-event', name: 'location_accepted');

    expect($location->fresh()->accepted)->toBeTrue();
});

test('all locations show when none is accepted yet', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Option One', 'accepted' => false]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Option Two', 'accepted' => false]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('Option One')
        ->assertSee('Option Two')
        ->assertDontSee('unvoted location');
});

test('once a location is accepted, unvoted locations collapse behind a toggle', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $accepted = Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Winner', 'accepted' => true]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Runner Up', 'accepted' => false]);
    Location::factory()->create(['trip_id' => $trip->id, 'name' => 'Also Ran', 'accepted' => false]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->assertSee('Winner')
        ->assertDontSee('Runner Up')
        ->assertDontSee('Also Ran')
        ->assertSee('Show 2 unvoted locations');

    $component->call('toggleShowAllLocations')
        ->assertSee('Winner')
        ->assertSee('Runner Up')
        ->assertSee('Also Ran')
        ->assertSee('Hide unvoted locations');

    $component->call('toggleShowAllLocations')
        ->assertDontSee('Runner Up')
        ->assertDontSee('Also Ran');
});

test('the unvoted-locations toggle does not appear when there is nothing to hide', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    Location::factory()->create(['trip_id' => $trip->id, 'accepted' => true]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('unvoted location');
});

test('unrelated user cannot accept a location', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id, 'accepted' => false]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('acceptLocation', $location->id)
        ->assertForbidden();
});

test('non-owner cannot delete a location', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteLocation', $location->id)
        ->assertForbidden();

    expect(Location::find($location->id))->not->toBeNull();
});

test('trip creator can delete a location', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteLocation', $location->id)
        ->assertDispatched('analytics-event', name: 'location_deleted');

    expect(Location::find($location->id))->toBeNull();
});

test('trip creator can toggle a vote', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('toggleVote', $location->id);

    expect($location->fresh()->hasVoteFrom($owner))->toBeTrue();
});

test('participant can toggle a vote', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($participant);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('toggleVote', $location->id);

    expect($location->fresh()->hasVoteFrom($participant))->toBeTrue();
});

test('unrelated user cannot toggle a vote', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $unrelated = User::factory()->create();
    $this->actingAs($unrelated);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('toggleVote', $location->id)
        ->assertForbidden();

    expect($location->fresh()->hasVoteFrom($unrelated))->toBeFalse();
});

test('unrelated user cannot open the voters modal', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('showVoters', $location->id)
        ->assertForbidden();
});

test('trip creator can open and close the voters modal', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->call('showVoters', $location->id)
        ->assertSet('showVotersModal', true)
        ->assertSet('selectedLocationId', $location->id);

    $component->call('closeVotersModal')
        ->assertSet('showVotersModal', false)
        ->assertSet('selectedLocationId', null);
});

test('selected location voters property is empty when nothing is selected', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSet('selectedLocationVoters', collect());
});

test('selected location voters property returns the voters', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $location->votes()->attach($owner->id);
    $this->actingAs($owner);

    $voters = Volt::test('trips.show', ['trip' => $trip])
        ->call('showVoters', $location->id)
        ->get('selectedLocationVoters');

    expect($voters->pluck('id'))->toContain($owner->id);
});

test('searchable users property is empty when search is blank', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertSet('searchableUsers', collect());
});

test('searchable users property matches by email, first name, and last name', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $byEmail = User::factory()->create(['email' => 'unique-match@example.com']);
    $byFirstName = User::factory()->create(['first_name' => 'Zebulon']);
    $byLastName = User::factory()->create(['last_name' => 'Zambrano']);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->set('participantSearch', 'unique-match@example.com');
    expect($component->get('searchableUsers')->pluck('id'))->toContain($byEmail->id);

    $component->set('participantSearch', 'Zebulon');
    expect($component->get('searchableUsers')->pluck('id'))->toContain($byFirstName->id);

    $component->set('participantSearch', 'Zambrano');
    expect($component->get('searchableUsers')->pluck('id'))->toContain($byLastName->id);
});

test('searchable users property excludes existing participants and the creator', function () {
    $owner = User::factory()->create(['email' => 'owner-search@example.com']);
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create(['email' => 'participant-search@example.com']);
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->set('participantSearch', 'search@example.com');

    $ids = $component->get('searchableUsers')->pluck('id');
    expect($ids)->not->toContain($owner->id);
    expect($ids)->not->toContain($participant->id);
});

test('searchable users property respects the limit of 10', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    User::factory()->count(15)->create(['first_name' => 'Searchable']);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->set('participantSearch', 'Searchable');

    expect($component->get('searchableUsers'))->toHaveCount(10);
});

test('non-owner cannot add a participant', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $newUser = User::factory()->create();
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('addParticipant', $newUser->id)
        ->assertForbidden();
});

test('owner can add a new participant', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $newUser = User::factory()->create();
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('addParticipant', $newUser->id)
        ->assertDispatched('analytics-event', name: 'trip_participant_added');

    expect($trip->fresh()->participants->pluck('id'))->toContain($newUser->id);
});

test('participants are assigned color slots in a fixed cycle that wraps back to slot 1', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $newUsers = User::factory()->count(6)->create();
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip]);
    foreach ($newUsers as $user) {
        $component->call('addParticipant', $user->id);
    }

    // Query the pivot table directly, ordered by its own id, so this doesn't
    // depend on the participants() relation's (unordered) default query.
    $slots = DB::table('trip_user')
        ->where('trip_id', $trip->id)
        ->orderBy('id')
        ->pluck('color_slot')
        ->map(fn ($slot) => (int) $slot);

    // Slot 1 is reserved for the creator, so participants cycle [2, 3, 5, 7, 1],
    // wrapping back to slot 1 (matching the creator's color) on the 6th person.
    expect($slots->values()->all())->toBe([2, 3, 5, 7, 1, 2]);
});

test('adding the creator as a participant is a no-op', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('addParticipant', $owner->id);

    expect($trip->fresh()->participants->pluck('id'))->not->toContain($owner->id);
});

test('adding an existing participant is a no-op', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('addParticipant', $participant->id);

    expect($trip->fresh()->participants->pluck('id')->filter(fn ($id) => $id === $participant->id))->toHaveCount(1);
});

test('non-owner cannot remove a participant', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('removeParticipant', $participant->id)
        ->assertForbidden();
});

test('owner can remove a participant', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('removeParticipant', $participant->id)
        ->assertDispatched('analytics-event', name: 'trip_participant_removed');

    expect($trip->fresh()->participants->pluck('id'))->not->toContain($participant->id);
});

test('add participant modal opens and closes, resetting the search', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->set('participantSearch', 'something')
        ->call('openAddParticipantModal')
        ->assertSet('showAddParticipantModal', true)
        ->assertSet('participantSearch', '');

    $component->set('participantSearch', 'something-else')
        ->call('closeAddParticipantModal')
        ->assertSet('showAddParticipantModal', false)
        ->assertSet('participantSearch', '');
});

test('add comment modal opens and closes', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->call('openAddCommentModal', $location->id)
        ->assertSet('showAddCommentModal', true)
        ->assertSet('selectedLocationIdForComment', $location->id);

    $component->call('closeAddCommentModal')
        ->assertSet('showAddCommentModal', false)
        ->assertSet('selectedLocationIdForComment', null);
});

test('addComment is a no-op when nothing is selected', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('addComment');

    expect(LocationComment::count())->toBe(0);
});

test('unrelated user cannot add a comment', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openAddCommentModal', $location->id)
        ->set("commentTexts.{$location->id}", 'Nice place')
        ->call('addComment')
        ->assertForbidden();
});

test('addComment validates blank and overly long content', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openAddCommentModal', $location->id)
        ->set("commentTexts.{$location->id}", '')
        ->call('addComment')
        ->assertHasErrors(["commentTexts.{$location->id}"]);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openAddCommentModal', $location->id)
        ->set("commentTexts.{$location->id}", str_repeat('a', 1001))
        ->call('addComment')
        ->assertHasErrors(["commentTexts.{$location->id}"]);
});

test('addComment creates a comment, clears the input, and closes the modal', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openAddCommentModal', $location->id)
        ->set("commentTexts.{$location->id}", 'Great spot!')
        ->call('addComment')
        ->assertSet("commentTexts.{$location->id}", '')
        ->assertSet('showAddCommentModal', false)
        ->assertDispatched('analytics-event', name: 'location_comment_added');

    expect(LocationComment::where('location_id', $location->id)->where('content', 'Great spot!')->exists())->toBeTrue();
});

test('comment owner can delete their own comment', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $comment = LocationComment::factory()->create(['location_id' => $location->id, 'user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteComment', $comment->id);

    expect(LocationComment::find($comment->id))->toBeNull();
});

test('trip creator can delete any comment', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $comment = LocationComment::factory()->create(['location_id' => $location->id, 'user_id' => $participant->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteComment', $comment->id);

    expect(LocationComment::find($comment->id))->toBeNull();
});

test('unrelated user cannot delete a comment', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $comment = LocationComment::factory()->create(['location_id' => $location->id, 'user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteComment', $comment->id)
        ->assertForbidden();

    expect(LocationComment::find($comment->id))->not->toBeNull();
});

test('toggleLocationComments expands then collapses', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $location = Location::factory()->create(['trip_id' => $trip->id]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->call('toggleLocationComments', $location->id)
        ->assertSet('expandedLocationId', $location->id);

    $component->call('toggleLocationComments', $location->id)
        ->assertSet('expandedLocationId', null);
});

test('expense owner can delete their expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteExpense', $expense->id)
        ->assertDispatched('analytics-event', name: 'expense_deleted');

    expect(Expense::find($expense->id))->toBeNull();
});

test('unrelated user cannot delete an expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('deleteExpense', $expense->id)
        ->assertForbidden();

    expect(Expense::find($expense->id))->not->toBeNull();
});

test('openEditExpenseModal populates fields and closeEditExpenseModal clears them', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'name' => 'Hotel',
        'unit_price' => 100.00,
        'quantity' => 2,
    ]);
    $this->actingAs($owner);

    $component = Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->assertSet('showEditExpenseModal', true)
        ->assertSet('editingExpenseId', $expense->id)
        ->assertSet('editingExpense.name', 'Hotel')
        ->assertSet('editingExpense.unit_price', '100.00');

    $component->call('closeEditExpenseModal')
        ->assertSet('showEditExpenseModal', false)
        ->assertSet('editingExpenseId', null);
});

test('changing the edit modal\'s currency prefills the exchange rate from the API', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['USD' => 1.0842]]),
    ]);

    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.currency', Currency::EUR->value)
        ->assertSet('editingExpense.exchange_rate', '1.0842');
});

test('openEditExpenseModal falls back user_id to the trip creator when the expense has no owner', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => null]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->assertSet('editingExpense.user_id', $owner->id);
});

test('unrelated user cannot start editing an expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->assertForbidden();
});

test('owner can save an edited expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'name' => 'Old Name',
        'unit_price' => 50.00,
        'quantity' => 1,
    ]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.name', 'New Name')
        ->call('saveExpense', $expense->id)
        ->assertSet('showEditExpenseModal', false)
        ->assertSet('editingExpenseId', null)
        ->assertDispatched('analytics-event', name: 'expense_updated');

    expect($expense->fresh()->name)->toBe('New Name');
});

test('unrelated user cannot save an edited expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'name' => 'Old Name']);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->call('saveExpense', $expense->id)
        ->assertForbidden();

    expect($expense->fresh()->name)->toBe('Old Name');
});

test('saveExpense validates the same rules as the expense form', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $unaffiliated = User::factory()->create();
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.name', '')
        ->call('saveExpense', $expense->id)
        ->assertHasErrors(['editingExpense.name']);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.user_id', $unaffiliated->id)
        ->call('saveExpense', $expense->id)
        ->assertHasErrors(['editingExpense.user_id']);
});

test('closing the edit expense modal clears validation errors for the next expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $firstExpense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $secondExpense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $firstExpense->id)
        ->set('editingExpense.name', '')
        ->call('saveExpense', $firstExpense->id)
        ->assertHasErrors(['editingExpense.name'])
        ->call('closeEditExpenseModal')
        ->assertHasNoErrors()
        ->call('openEditExpenseModal', $secondExpense->id)
        ->assertHasNoErrors();
});
