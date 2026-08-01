<?php

namespace App\Actions\Expenses;

use Illuminate\Support\Collection;

class CalculateSettlementPlan
{
    /** @var list<int> */
    private array $userIds;

    /** @var list<int> */
    private array $amounts;

    /** @var list<array{from: int, to: int, amount: int}> */
    private array $path;

    /** @var list<array{from: int, to: int, amount: int}> */
    private array $best;

    private int $bestCount;

    /**
     * Compute the minimal-transaction transfer plan that settles every
     * balance, via DFS/backtracking over non-zero balances (the classic
     * "optimal account balancing" problem).
     *
     * @param  Collection<int, int>  $balancesInCents  keyed by user_id => signed cents (+owed / -owes)
     * @return list<array{from: int, to: int, amount: int}>
     */
    public function calculate(Collection $balancesInCents): array
    {
        $nonZero = $balancesInCents->filter(fn (int $cents) => $cents !== 0);

        if ($nonZero->isEmpty()) {
            return [];
        }

        $this->userIds = $nonZero->keys()->values()->all();
        $this->amounts = $nonZero->values()->all();
        $this->path = [];
        $this->best = [];
        $this->bestCount = PHP_INT_MAX;

        $this->dfs(0);

        return $this->best;
    }

    private function dfs(int $start): void
    {
        $n = count($this->amounts);

        while ($start < $n && $this->amounts[$start] === 0) {
            $start++;
        }

        if ($start === $n) {
            if (count($this->path) < $this->bestCount) {
                $this->bestCount = count($this->path);
                $this->best = $this->path;
            }

            return;
        }

        // At least one more transfer is required to resolve $amounts[$start];
        // if that alone can't beat the current best, stop exploring this branch.
        if (count($this->path) + 1 >= $this->bestCount) {
            return;
        }

        for ($i = $start + 1; $i < $n; $i++) {
            if ($this->amounts[$i] === 0) {
                continue;
            }

            $sameSign = ($this->amounts[$i] > 0) === ($this->amounts[$start] > 0);
            if ($sameSign) {
                continue;
            }

            $transferAmount = abs($this->amounts[$start]);
            $transfer = $this->amounts[$start] < 0
                ? ['from' => $this->userIds[$start], 'to' => $this->userIds[$i], 'amount' => $transferAmount]
                : ['from' => $this->userIds[$i], 'to' => $this->userIds[$start], 'amount' => $transferAmount];

            $this->amounts[$i] += $this->amounts[$start];
            $this->path[] = $transfer;

            $this->dfs($start + 1);

            array_pop($this->path);
            $this->amounts[$i] -= $this->amounts[$start];
        }
    }
}
