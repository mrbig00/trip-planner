<?php

use App\Enums\ExpenseSplitType;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('openEditExpenseModal repopulates split state from existing shares', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 100.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Percentage->value,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 40.00, 'percentage' => 40],
        ['user_id' => $participant->id, 'amount' => 60.00, 'percentage' => 60],
    ]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->assertSet('editingExpense.split_type', 'percentage')
        ->assertSet('editingExpense.percentages.'.$owner->id, '40.00')
        ->assertSet('editingExpense.percentages.'.$participant->id, '60.00');
});

test('saving an edited expense replaces shares rather than accumulating them', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 40.00,
        'quantity' => 1,
        'split_type' => ExpenseSplitType::Equal->value,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 20.00],
        ['user_id' => $participant->id, 'amount' => 20.00],
    ]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.split_type', 'fixed')
        ->set('editingExpense.participant_ids', [$owner->id])
        ->set('editingExpense.fixed_amounts', [$owner->id => '40.00'])
        ->call('saveExpense', $expense->id)
        ->assertSet('showEditExpenseModal', false);

    $expense->refresh();
    $shares = $expense->shares()->get();

    expect($shares)->toHaveCount(1)
        ->and($shares->first()->user_id)->toBe($owner->id)
        ->and((float) $shares->first()->amount)->toBe(40.00)
        ->and($expense->split_type)->toBe(ExpenseSplitType::Fixed);
});

test('unrelated user cannot save split changes to an expense', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs(User::factory()->create());

    Volt::test('trips.show', ['trip' => $trip])
        ->set('editingExpense.participant_ids', [$owner->id])
        ->call('saveExpense', $expense->id)
        ->assertForbidden();
});

test('duplicate participant ids fail validation instead of hitting the database', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $expense = Expense::factory()->create(['trip_id' => $trip->id, 'user_id' => $owner->id]);
    $this->actingAs($owner);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('openEditExpenseModal', $expense->id)
        ->set('editingExpense.participant_ids', [$owner->id, $owner->id])
        ->call('saveExpense', $expense->id)
        ->assertHasErrors(['editingExpense.participant_ids.0', 'editingExpense.participant_ids.1']);
});
