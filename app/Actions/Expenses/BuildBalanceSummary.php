<?php

namespace App\Actions\Expenses;

use App\Models\User;
use Illuminate\Support\Collection;

class BuildBalanceSummary
{
    /**
     * Build display-ready balance rows and the minimal settlement transfer
     * plan for a set of signed balances in cents. Used identically by the
     * per-trip Settle Up card and the cross-trip global Settle Up page —
     * only the balances fed in (one trip's vs. every visible trip's summed
     * together) differ.
     *
     * @param  Collection<int, int>  $balancesInCents  keyed by user_id => signed cents (+owed / -owes)
     * @param  Collection<int, User>  $users  keyed by user_id
     * @return array{balances: Collection<int, array{user: ?User, balanceCents: int}>, transfers: list<array{from: ?User, to: ?User, amountCents: int}>}
     */
    public function build(Collection $balancesInCents, Collection $users): array
    {
        $balances = $balancesInCents->map(fn (int $cents, int $userId) => [
            'user' => $users->get($userId),
            'balanceCents' => $cents,
        ])->values();

        $transfers = collect(app(CalculateSettlementPlan::class)->calculate($balancesInCents))
            ->map(fn (array $transfer) => [
                'from' => $users->get($transfer['from']),
                'to' => $users->get($transfer['to']),
                'amountCents' => $transfer['amount'],
            ])->all();

        return ['balances' => $balances, 'transfers' => $transfers];
    }
}
