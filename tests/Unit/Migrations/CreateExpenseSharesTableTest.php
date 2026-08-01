<?php

use App\Models\Expense;
use App\Models\ExpenseShare;
use App\Models\Trip;
use App\Models\User;

test('backfilling equal shares is correct and idempotent on a real double-run', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 15.01,
        'quantity' => 1,
    ]);

    expect(ExpenseShare::where('expense_id', $expense->id)->count())->toBe(0);

    $migration = require database_path('migrations/2026_08_02_000001_create_expense_shares_table.php');

    $migration->backfillEqualSharesForExistingExpenses();

    $shares = ExpenseShare::where('expense_id', $expense->id)->orderBy('user_id')->get();

    expect($shares)->toHaveCount(2)
        ->and((float) $shares->sum('amount'))->toBe(15.01);

    // Re-running must not duplicate rows for an expense that already has shares.
    $migration->backfillEqualSharesForExistingExpenses();

    expect(ExpenseShare::where('expense_id', $expense->id)->count())->toBe(2);
});
