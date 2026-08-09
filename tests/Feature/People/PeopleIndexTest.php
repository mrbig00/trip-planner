<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access the people page', function () {
    $response = $this->get(route('people.index'));
    $response->assertRedirect(route('login'));
});

test('a person you have never shared a trip with does not appear', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create(['first_name' => 'Complete', 'last_name' => 'Stranger']);
    Trip::factory()->create(['user_id' => $stranger->id]);
    $this->actingAs($user);

    Volt::test('people.index')->assertDontSee('Complete Stranger');
});

test('a fellow participant appears with the correct trip count', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create(['first_name' => 'Alex', 'last_name' => 'Companion']);
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'name' => 'Road Trip']);
    $trip->participants()->attach($companion->id);
    $this->actingAs($owner);

    Volt::test('people.index')
        ->assertSee('Alex Companion')
        ->assertSee('1 trip together')
        ->assertSee('Road Trip');
});

test('a trip creator appears as a companion to their participants', function () {
    $creator = User::factory()->create(['first_name' => 'Trip', 'last_name' => 'Creator']);
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    Volt::test('people.index')->assertSee('Trip Creator');
});

test('an imbalanced trip shows the correct owed amount and direction for both sides', function () {
    $owner = User::factory()->create(['first_name' => 'Owner', 'last_name' => 'User']);
    $companion = User::factory()->create(['first_name' => 'Companion', 'last_name' => 'User']);
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($companion->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 100,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expense->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $this->actingAs($owner);
    Volt::test('people.index')->assertSee('$50.00 owed to you');

    $this->actingAs($companion);
    Volt::test('people.index')->assertSee('$50.00 you owe') ;
});

test('a settled-up trip shows "Settled up"', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create(['first_name' => 'Even', 'last_name' => 'Steven']);
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($companion->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 100,
        'quantity' => 1,
    ]);
    $expense->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expense->shares()->create(['user_id' => $companion->id, 'amount' => 50]);
    $trip->settlements()->create(['from_user_id' => $companion->id, 'to_user_id' => $owner->id, 'amount_cents' => 5000]);

    $this->actingAs($owner);

    Volt::test('people.index')->assertSee('Settled up');
});

test('the same companion across multiple trips is shown once, with aggregated trip count and balance', function () {
    $owner = User::factory()->create();
    $companion = User::factory()->create(['first_name' => 'Repeat', 'last_name' => 'Traveler']);

    $tripOne = Trip::factory()->create(['user_id' => $owner->id, 'name' => 'Trip One']);
    $tripOne->participants()->attach($companion->id);
    $expenseOne = Expense::factory()->create(['trip_id' => $tripOne->id, 'user_id' => $owner->id, 'unit_price' => 100, 'quantity' => 1]);
    $expenseOne->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $expenseOne->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $tripTwo = Trip::factory()->create(['user_id' => $owner->id, 'name' => 'Trip Two']);
    $tripTwo->participants()->attach($companion->id);
    $expenseTwo = Expense::factory()->create(['trip_id' => $tripTwo->id, 'user_id' => $owner->id, 'unit_price' => 40, 'quantity' => 1]);
    $expenseTwo->shares()->create(['user_id' => $owner->id, 'amount' => 20]);
    $expenseTwo->shares()->create(['user_id' => $companion->id, 'amount' => 20]);

    $this->actingAs($owner);

    $html = Volt::test('people.index')->html();

    // Exactly one row rendered for this companion (not once per shared trip).
    expect(substr_count($html, "companion-{$companion->id}"))->toBe(1);

    Volt::test('people.index')
        ->assertSee('2 trips together')
        ->assertSee('Trip One')
        ->assertSee('Trip Two')
        ->assertSee('$70.00 owed to you');
});

test('on a 3+ person trip, a companion\'s balance reflects their own direct transactions, not an arbitrary settlement-plan pairing', function () {
    // Viewer directly paid for D's $50 share (D genuinely owes the viewer).
    // B separately paid for C's $50 share (C genuinely owes B, not the viewer).
    // Naively summing minimal-transaction settlement-plan transfers here can
    // pick the opposite (equally balance-minimal) pairing and misattribute
    // both debts to the wrong companion — this asserts the real ledger wins.
    $viewer = User::factory()->create();
    $b = User::factory()->create(['first_name' => 'B', 'last_name' => 'Payer']);
    $c = User::factory()->create(['first_name' => 'C', 'last_name' => 'NotViewersDebtor']);
    $d = User::factory()->create(['first_name' => 'D', 'last_name' => 'ViewersDebtor']);

    $trip = Trip::factory()->create(['user_id' => $viewer->id]);
    $trip->participants()->attach([$b->id, $c->id, $d->id]);

    $paidForD = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $viewer->id, 'unit_price' => 50, 'quantity' => 1]);
    $paidForD->shares()->create(['user_id' => $d->id, 'amount' => 50]);

    $paidForC = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $b->id, 'unit_price' => 50, 'quantity' => 1]);
    $paidForC->shares()->create(['user_id' => $c->id, 'amount' => 50]);

    $this->actingAs($viewer);

    $html = Volt::test('people.index')->html();

    // All three still appear as companions (they share the trip)...
    expect($html)->toContain('B Payer');
    expect($html)->toContain('C NotViewersDebtor');
    expect($html)->toContain('D ViewersDebtor');

    // ...but only D — who the viewer actually transacted with — owes them
    // anything; B and C (who transacted with each other, not the viewer)
    // are correctly settled up from the viewer's perspective.
    expect(substr_count($html, '$50.00 owed to you'))->toBe(1);
    expect(substr_count($html, 'Settled up'))->toBe(2);
});
