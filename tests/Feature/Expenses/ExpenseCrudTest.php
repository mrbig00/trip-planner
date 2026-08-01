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

test('participants cannot access expense create (creator only)', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $participant = User::factory()->create();
    $trip->participants()->attach($participant->id);
    $this->actingAs($participant);

    $response = $this->get(route('expenses.create', $trip));
    $response->assertForbidden();
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

