<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access expense create', function () {
    $trip = Trip::factory()->create();

    $response = $this->get(route('expenses.create', $trip));
    $response->assertRedirect(route('login'));
});

test('guests cannot access expense edit', function () {
    $trip = Trip::factory()->create();
    $expense = Expense::factory()->create(['trip_id' => $trip->id]);

    $response = $this->get(route('expenses.edit', [$trip, $expense]));
    $response->assertRedirect(route('login'));
});

test('participants cannot access expense create (creator only)', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    $response = $this->get(route('expenses.create', $trip));
    $response->assertForbidden();
});

test('expense owner can access expense edit', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expenseOwner = User::factory()->create();
    $trip->participants()->attach($expenseOwner->id);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $expenseOwner->id]);
    $this->actingAs($expenseOwner);

    $response = $this->get(route('expenses.edit', [$trip, $expense]));
    $response->assertSuccessful();
});

test('trip creator can access expense edit for any expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expenseOwner = User::factory()->create();
    $trip->participants()->attach($expenseOwner->id);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $expenseOwner->id]);
    $this->actingAs($owner);

    $response = $this->get(route('expenses.edit', [$trip, $expense]));
    $response->assertSuccessful();
});

test('unrelated participant cannot access expense edit', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expenseOwner = User::factory()->create();
    $trip->participants()->attach($expenseOwner->id);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $expenseOwner->id]);

    $unrelated = User::factory()->create();
    $trip->participants()->attach($unrelated->id);
    $this->actingAs($unrelated);

    $response = $this->get(route('expenses.edit', [$trip, $expense]));
    $response->assertForbidden();
});

test('mismatched trip and expense returns not found on edit', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expenseFromOtherTrip = Expense::factory()->create();
    $this->actingAs($owner);

    $response = $this->get(route('expenses.edit', [$trip, $expenseFromOtherTrip]));
    $response->assertNotFound();
});

test('create mount defaults user_id to the authenticated user', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->assertSet('user_id', $owner->id);
});

test('expense creation rejects a user_id not in participants or creator', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $unaffiliated = User::factory()->create();
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('user_id', $unaffiliated->id)
        ->call('store')
        ->assertHasErrors(['user_id']);
});

test('expense creation accepts the creator as user_id', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('user_id', $owner->id)
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    expect(Expense::where('name', 'Dinner')->exists())->toBeTrue();
});

test('expense creation accepts a participant as user_id', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('user_id', $participant->id)
        ->call('store')
        ->assertRedirect(route('trips.show', $trip));

    $expense = Expense::where('name', 'Dinner')->first();
    expect($expense->user_id)->toBe($participant->id);
});

test('expense creation requires a name', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', '')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->call('store')
        ->assertHasErrors(['name']);
});

test('expense creation rejects a negative unit price', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '-5')
        ->set('quantity', 2)
        ->call('store')
        ->assertHasErrors(['unit_price']);
});

test('expense creation rejects a non-numeric unit price', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', 'abc')
        ->set('quantity', 2)
        ->call('store')
        ->assertHasErrors(['unit_price']);
});

test('expense creation rejects a quantity less than 1', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 0)
        ->call('store')
        ->assertHasErrors(['quantity']);
});

test('expense creation rejects an invalid link', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('link', 'not-a-url')
        ->call('store')
        ->assertHasErrors(['link']);
});

test('edit mount pre-fills expense fields', function () {
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

    Volt::test('expenses.edit', ['trip' => $trip, 'expense' => $expense])
        ->assertSet('name', 'Hotel')
        ->assertSet('unit_price', '100.00')
        ->assertSet('quantity', 2)
        ->assertSet('user_id', $owner->id);
});

test('edit mount falls back user_id to the trip creator when the expense has no owner', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => null]);
    $this->actingAs($owner);

    Volt::test('expenses.edit', ['trip' => $trip, 'expense' => $expense])
        ->assertSet('user_id', $owner->id);
});

test('owner can update an expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id, 'name' => 'Old Name']);
    $this->actingAs($owner);

    Volt::test('expenses.edit', ['trip' => $trip, 'expense' => $expense])
        ->set('name', 'New Name')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip));

    expect($expense->fresh()->name)->toBe('New Name');
});
