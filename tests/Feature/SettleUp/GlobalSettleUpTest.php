<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access the global settle up page', function () {
    $response = $this->get(route('settle-up.index'));
    $response->assertRedirect(route('login'));
});

test('an empty state shows when the user has no trips', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settle-up.index')->assertSee("haven't shared a trip");
});

test('a single trip imbalance shows the correct balances and one suggested transfer', function () {
    $owner = User::factory()->create(['first_name' => 'Owner', 'last_name' => 'One']);
    $companion = User::factory()->create(['first_name' => 'Buddy', 'last_name' => 'One']);
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($companion->id);

    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'unit_price' => 100, 'quantity' => 1]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expense->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $this->actingAs($owner);

    Volt::test('settle-up.index')
        ->assertSee('Owner One')
        ->assertSee('Buddy One')
        ->assertSee('is owed $50.00')
        ->assertSee('owes $50.00')
        ->assertSee('$50.00');
});

test('the same pair across two trips nets into a single combined transfer', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create(['first_name' => 'Repeat', 'last_name' => 'Pair']);

    $tripOne = Trip::factory()->create(['user_id' => $owner->id]);
    $tripOne->participants()->attach($companion->id);
    $expenseOne = Expense::factory()->create(['trip_id' => $tripOne->id, 'user_id' => $owner->id, 'unit_price' => 100, 'quantity' => 1]);
    $expenseOne->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expenseOne->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $tripTwo = Trip::factory()->create(['user_id' => $owner->id]);
    $tripTwo->participants()->attach($companion->id);
    $expenseTwo = Expense::factory()->create(['trip_id' => $tripTwo->id, 'user_id' => $companion->id, 'unit_price' => 40, 'quantity' => 1]);
    $expenseTwo->shares()->create(['user_id' => $owner->id, 'amount' => 20]);
    $expenseTwo->shares()->create(['user_id' => $companion->id, 'amount' => 20]);

    // Trip one: companion owes owner $50. Trip two: owner owes companion $20.
    // Combined net: companion owes owner $30 — one transfer, not two.
    $this->actingAs($owner);

    $component = Volt::test('settle-up.index');
    $component->assertSee('$30.00');

    // Exactly one transfer row rendered for this pair (not one per trip).
    $html = $component->html();
    expect(substr_count($html, "transfer-{$companion->id}-{$owner->id}"))->toBe(1);
    expect(substr_count($html, "transfer-{$owner->id}-{$companion->id}"))->toBe(0);
});

test('global balances reconcile with the sum of each trip\'s own balance', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create();

    $tripOne = Trip::factory()->create(['user_id' => $owner->id]);
    $tripOne->participants()->attach($companion->id);
    $expenseOne = Expense::factory()->create(['trip_id' => $tripOne->id, 'user_id' => $owner->id, 'unit_price' => 100, 'quantity' => 1]);
    $expenseOne->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expenseOne->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $tripTwo = Trip::factory()->create(['user_id' => $owner->id]);
    $tripTwo->participants()->attach($companion->id);
    $expenseTwo = Expense::factory()->create(['trip_id' => $tripTwo->id, 'user_id' => $owner->id, 'unit_price' => 60, 'quantity' => 1]);
    $expenseTwo->shares()->create(['user_id' => $owner->id, 'amount' => 30]);
    $expenseTwo->shares()->create(['user_id' => $companion->id, 'amount' => 30]);

    $expectedOwnerTotal = $tripOne->fresh(['expenses.shares', 'participants', 'creator'])->balances()->get($owner->id)
        + $tripTwo->fresh(['expenses.shares', 'participants', 'creator'])->balances()->get($owner->id);

    expect($expectedOwnerTotal)->toBe(8000); // $50 + $30 owed to the owner

    $this->actingAs($owner);
    Volt::test('settle-up.index')->assertSee('is owed $80.00');
});

test('everyone settled up shows the settled-up state with zero transfers', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($companion->id);

    $this->actingAs($owner);

    Volt::test('settle-up.index')->assertSee('Everyone is settled up!');
});

test('users only see balances from trips they created or participate in', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create(['first_name' => 'Unrelated', 'last_name' => 'Person']);
    $otherTrip = Trip::factory()->create(['user_id' => $stranger->id]);
    Expense::factory()->create(['trip_id' => $otherTrip->id, 'user_id' => $stranger->id, 'unit_price' => 500, 'quantity' => 1]);
    $this->actingAs($user);

    Volt::test('settle-up.index')->assertDontSee('Unrelated Person');
});
