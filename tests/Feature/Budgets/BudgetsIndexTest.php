<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access the budgets page', function () {
    $response = $this->get(route('budgets.index'));
    $response->assertRedirect(route('login'));
});

test('a trip with no budget set is shown distinctly', function () {
    $user = User::factory()->create();
    Trip::factory()->create(['user_id' => $user->id, 'name' => 'Undated Getaway', 'budget' => null]);
    $this->actingAs($user);

    Volt::test('budgets.index')
        ->assertSee('Undated Getaway')
        ->assertSee('No budget set');
});

test('a trip under budget shows spent, budget, and remaining', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Beach Trip', 'budget' => 200]);
    Expense::factory()->create(['trip_id' => $trip->id, 'unit_price' => 50, 'quantity' => 1]);
    $this->actingAs($user);

    Volt::test('budgets.index')
        ->assertSee('Beach Trip')
        ->assertSee('$150.00 remaining')
        ->assertSee('$50.00 / $200.00')
        ->assertDontSee('over budget');
});

test('an over-budget trip is flagged instead of silently capped', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Overspent Trip', 'budget' => 100]);
    Expense::factory()->create(['trip_id' => $trip->id, 'unit_price' => 150, 'quantity' => 1]);
    $this->actingAs($user);

    Volt::test('budgets.index')
        ->assertSee('Overspent Trip')
        ->assertSee('$50.00 over budget');
});

test('the most over-budget trip is ranked before one with room left, which ranks before one with no budget', function () {
    $user = User::factory()->create();

    $overBudget = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Way Over', 'budget' => 100]);
    Expense::factory()->create(['trip_id' => $overBudget->id, 'unit_price' => 300, 'quantity' => 1]);

    $underBudget = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Plenty Left', 'budget' => 1000]);
    Expense::factory()->create(['trip_id' => $underBudget->id, 'unit_price' => 10, 'quantity' => 1]);

    $noBudget = Trip::factory()->create(['user_id' => $user->id, 'name' => 'No Budget Trip', 'budget' => null]);

    $this->actingAs($user);

    $html = Volt::test('budgets.index')->html();

    $overPos = strpos($html, 'Way Over');
    $underPos = strpos($html, 'Plenty Left');
    $noBudgetPos = strpos($html, 'No Budget Trip');

    expect($overPos)->not->toBeFalse();
    expect($underPos)->not->toBeFalse();
    expect($noBudgetPos)->not->toBeFalse();
    expect($overPos)->toBeLessThan($underPos);
    expect($underPos)->toBeLessThan($noBudgetPos);
});

test('users only see trips they created or participate in', function () {
    $user = User::factory()->create();
    $myTrip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'My Own Trip']);
    $otherTrip = Trip::factory()->create(['name' => 'Someone Elses Trip']);
    $this->actingAs($user);

    Volt::test('budgets.index')
        ->assertSee('My Own Trip')
        ->assertDontSee('Someone Elses Trip');
});

test('a trip a user participates in (but did not create) still appears', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id, 'name' => 'Shared Trip', 'budget' => 500]);
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    Volt::test('budgets.index')->assertSee('Shared Trip');
});

test('budgets page figures reconcile with the trip detail page', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'name' => 'Reconciliation Trip', 'budget' => 300]);
    Expense::factory()->create(['trip_id' => $trip->id, 'unit_price' => 120, 'quantity' => 1]);
    $this->actingAs($user);

    Volt::test('budgets.index')->assertSee('$120.00 / $300.00');
    Volt::test('trips.show', ['trip' => $trip])->assertSee('$120.00 / $300.00');
});
