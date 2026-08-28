<?php

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use App\Models\Expense;
use Livewire\Volt\Volt;

test('trips in different currencies are never summed into one figure', function () {
    $owner = User::factory()->create(['first_name' => 'Owner', 'last_name' => 'One']);
    $companion = User::factory()->create(['first_name' => 'Buddy', 'last_name' => 'One']);

    $usdTrip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::USD->value]);
    $usdTrip->participants()->attach($companion->id);
    $usdExpense = Expense::factory()->create([
        'trip_id' => $usdTrip->id, 'user_id' => $owner->id, 'unit_price' => 100, 'quantity' => 1,
        'currency' => Currency::USD->value,
    ]);
    $usdExpense->shares()->create(['user_id' => $owner->id, 'amount' => 50]);
    $usdExpense->shares()->create(['user_id' => $companion->id, 'amount' => 50]);

    $eurTrip = Trip::factory()->create(['user_id' => $owner->id, 'currency' => Currency::EUR->value]);
    $eurTrip->participants()->attach($companion->id);
    $eurExpense = Expense::factory()->create([
        'trip_id' => $eurTrip->id, 'user_id' => $owner->id, 'unit_price' => 40, 'quantity' => 1,
        'currency' => Currency::EUR->value,
    ]);
    $eurExpense->shares()->create(['user_id' => $owner->id, 'amount' => 20]);
    $eurExpense->shares()->create(['user_id' => $companion->id, 'amount' => 20]);

    // Companion owes owner $50 on the USD trip and €20 on the EUR trip — two
    // separate figures, never blended into a single combined number.
    $this->actingAs($owner);

    $component = Volt::test('settle-up.index');
    $component->assertSee('is owed $50.00')
        ->assertSee('is owed €20.00')
        ->assertDontSee('$70.00')
        ->assertDontSee('70.00');

    $component->assertSee('USD')->assertSee('EUR');
});
