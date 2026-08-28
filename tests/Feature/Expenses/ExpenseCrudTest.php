<?php

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use App\Models\Expense;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Http;

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
        ->assertRedirect(route('trips.show', $trip))
        ->assertDispatched('analytics-event', name: 'expense_created');

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

test('create mount defaults currency to the trip\'s currency', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::EUR->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->assertSet('currency', Currency::EUR->value);
});

test('an expense in the trip\'s own currency needs no exchange rate', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('currency', Currency::USD->value)
        ->call('store')
        ->assertHasNoErrors();

    $expense = Expense::where('name', 'Dinner')->first();
    expect($expense->currency)->toBe(Currency::USD)
        ->and($expense->exchange_rate)->toBeNull();
});

test('an expense in a different currency requires an exchange rate', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('currency', Currency::EUR->value)
        ->call('store')
        ->assertHasErrors(['exchange_rate']);
});

test('an expense in a different currency persists with its exchange rate', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('name', 'Dinner')
        ->set('unit_price', '20')
        ->set('quantity', 2)
        ->set('currency', Currency::EUR->value)
        ->set('exchange_rate', '1.1')
        ->call('store')
        ->assertHasNoErrors();

    $expense = Expense::where('name', 'Dinner')->first();
    expect($expense->currency)->toBe(Currency::EUR)
        ->and((float) $expense->exchange_rate)->toBe(1.1);
});

test('selecting a different currency prefills the exchange rate from the API', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['USD' => 1.0842]]),
    ]);

    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('currency', Currency::EUR->value)
        ->assertSet('exchange_rate', '1.0842');
});

test('switching back to the trip\'s own currency clears the prefilled exchange rate', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(['rates' => ['USD' => 1.0842]]),
    ]);

    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('currency', Currency::EUR->value)
        ->assertSet('exchange_rate', '1.0842')
        ->set('currency', Currency::USD->value)
        ->assertSet('exchange_rate', null);
});

test('a failed exchange rate lookup leaves the field blank for manual entry', function () {
    Http::fake([
        'api.frankfurter.dev/*' => Http::response(null, 500),
    ]);

    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $this->actingAs($owner);

    Volt::test('expenses.create', ['trip' => $trip])
        ->set('currency', Currency::EUR->value)
        ->assertSet('exchange_rate', null);
});
