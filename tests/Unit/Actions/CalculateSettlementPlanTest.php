<?php

use App\Actions\Expenses\CalculateSettlementPlan;
use Illuminate\Support\Collection;

function naiveGreedySettlement(array $balances): array
{
    $transfers = [];

    while (true) {
        $creditorId = null;
        $debtorId = null;
        $maxCredit = 0;
        $maxDebt = 0;

        foreach ($balances as $userId => $amount) {
            if ($amount > $maxCredit) {
                $maxCredit = $amount;
                $creditorId = $userId;
            }
            if ($amount < $maxDebt) {
                $maxDebt = $amount;
                $debtorId = $userId;
            }
        }

        if ($creditorId === null || $debtorId === null) {
            break;
        }

        $amount = min($maxCredit, -$maxDebt);
        $transfers[] = ['from' => $debtorId, 'to' => $creditorId, 'amount' => $amount];
        $balances[$creditorId] -= $amount;
        $balances[$debtorId] += $amount;
    }

    return $transfers;
}

test('trivial two-person case produces one transfer', function () {
    $plan = (new CalculateSettlementPlan)->calculate(new Collection([1 => -500, 2 => 500]));

    expect($plan)->toHaveCount(1)
        ->and($plan[0])->toBe(['from' => 1, 'to' => 2, 'amount' => 500]);
});

test('all-zero balances produce no transfers', function () {
    $plan = (new CalculateSettlementPlan)->calculate(new Collection([1 => 0, 2 => 0, 3 => 0]));

    expect($plan)->toBe([]);
});

test('optimal algorithm beats naive greedy on a verified fixture', function () {
    $balances = ['A' => -800, 'B' => -700, 'C' => 700, 'D' => 200, 'E' => 600];

    $optimal = (new CalculateSettlementPlan)->calculate(new Collection($balances));
    $greedy = naiveGreedySettlement($balances);

    expect($optimal)->toHaveCount(3);
    expect($greedy)->toHaveCount(4);
});

test('the returned transfer plan replays to reconstruct the original balances', function () {
    $balances = ['A' => -800, 'B' => -700, 'C' => 700, 'D' => 200, 'E' => 600];

    $plan = (new CalculateSettlementPlan)->calculate(new Collection($balances));

    $ledger = array_fill_keys(array_keys($balances), 0);
    foreach ($plan as $transfer) {
        $ledger[$transfer['from']] -= $transfer['amount'];
        $ledger[$transfer['to']] += $transfer['amount'];
    }

    expect($ledger)->toBe($balances);
});

test('a single unmatched non-zero balance across all others still settles exactly', function () {
    $balances = [1 => -300, 2 => 100, 3 => 100, 4 => 100];

    $plan = (new CalculateSettlementPlan)->calculate(new Collection($balances));

    $ledger = array_fill_keys(array_keys($balances), 0);
    foreach ($plan as $transfer) {
        $ledger[$transfer['from']] -= $transfer['amount'];
        $ledger[$transfer['to']] += $transfer['amount'];
    }

    expect($ledger)->toBe($balances)
        ->and(count($plan))->toBeLessThanOrEqual(3);
});
