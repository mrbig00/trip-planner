<?php

use App\Models\Trip;
use App\Models\User;
use App\Support\Money;
use App\Models\Expense;
use Livewire\Volt\Volt;
use App\Enums\ExpenseSplitType;

test('cost breakdown totals reconcile exactly with the expenses total', function () {
    $owner = User::factory()->create();
    $participantA = User::factory()->create();
    $participantB = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach([$participantA->id, $participantB->id]);

    $equalExpense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 30.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Equal->value,
    ]);
    $equalExpense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participantA->id, 'amount' => 10.00],
        ['user_id' => $participantB->id, 'amount' => 10.00],
    ]);

    $percentageExpense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $participantA->id,
        'unit_price' => 100.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Percentage->value,
    ]);
    $percentageExpense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 25.00, 'percentage' => 25],
        ['user_id' => $participantB->id, 'amount' => 75.00, 'percentage' => 75],
    ]);

    $this->actingAs($owner);
    $component = Volt::test('trips.show', ['trip' => $trip]);

    $breakdown = $component->get('costBreakdown');
    $totalExpenses = $component->get('totalExpenses');

    $breakdownTotalCents = $breakdown->sum('amountCents');

    // sortByDesc('amountCents') orders this participantB (8500), owner (3500),
    // then participantA (1000).
    expect($breakdownTotalCents)->toBe(Money::toCents((string) $totalExpenses))
        ->and($breakdown->pluck('amountCents', 'user.id')->all())->toBe([
            $participantB->id => 8500,
            $owner->id => 3500,
            $participantA->id => 1000,
        ]);
});

test('cost breakdown is empty when there are no expenses', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->assertDontSee('Cost Breakdown');
});
