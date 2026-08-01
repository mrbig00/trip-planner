<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseSplitType;
use App\Support\Money;

class BuildExpenseShares
{
    /**
     * Build the expense_shares rows for a given split, guaranteeing the
     * stored amounts always sum to exactly $totalCents.
     *
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $percentages  keyed by user id
     * @param  array<int, string>  $fixedAmounts  keyed by user id
     * @return list<array{user_id: int, amount: string, percentage: ?string}>
     */
    public function build(int $totalCents, ExpenseSplitType $splitType, array $participantIds, array $percentages, array $fixedAmounts): array
    {
        return match ($splitType) {
            ExpenseSplitType::Equal => $this->buildEqual($totalCents, $participantIds),
            ExpenseSplitType::Percentage => $this->buildPercentage($totalCents, $participantIds, $percentages),
            ExpenseSplitType::Fixed => $this->buildFixed($participantIds, $fixedAmounts),
        };
    }

    /**
     * @param  array<int, int>  $participantIds
     * @return list<array{user_id: int, amount: string, percentage: ?string}>
     */
    private function buildEqual(int $totalCents, array $participantIds): array
    {
        $ids = collect($participantIds)->sort()->values()->all();
        $count = count($ids);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;

        return array_map(function ($userId, $index) use ($base, $remainder) {
            $cents = $base + ($index < $remainder ? 1 : 0);

            return [
                'user_id' => $userId,
                'amount' => Money::fromCents($cents),
                'percentage' => null,
            ];
        }, $ids, array_keys($ids));
    }

    /**
     * Largest-remainder method (Hamilton's method): compute the exact share
     * per person, floor to cents, then distribute the leftover (or clawed
     * back) cents to whoever has the largest (or smallest) fractional
     * remainder, breaking ties by user id for determinism.
     *
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $percentages
     * @return list<array{user_id: int, amount: string, percentage: ?string}>
     */
    private function buildPercentage(int $totalCents, array $participantIds, array $percentages): array
    {
        $ids = collect($participantIds)->sort()->values()->all();

        $exact = [];
        $floors = [];
        foreach ($ids as $userId) {
            $exact[$userId] = bcdiv(bcmul((string) $totalCents, (string) $percentages[$userId], 10), '100', 10);
            $floors[$userId] = (int) bcadd($exact[$userId], '0', 0);
        }

        $remainingCents = $totalCents - array_sum($floors);

        $fractions = [];
        foreach ($ids as $userId) {
            $fractions[$userId] = (float) bcsub($exact[$userId], (string) $floors[$userId], 10);
        }

        $order = $ids;
        usort($order, fn ($a, $b) => ($fractions[$b] <=> $fractions[$a]) ?: ($a <=> $b));

        $amounts = $floors;
        if ($remainingCents > 0) {
            foreach (array_slice($order, 0, $remainingCents) as $userId) {
                $amounts[$userId]++;
            }
        } elseif ($remainingCents < 0) {
            foreach (array_slice(array_reverse($order), 0, abs($remainingCents)) as $userId) {
                $amounts[$userId]--;
            }
        }

        return array_map(fn ($userId) => [
            'user_id' => $userId,
            'amount' => Money::fromCents($amounts[$userId]),
            'percentage' => (string) $percentages[$userId],
        ], $ids);
    }

    /**
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $fixedAmounts
     * @return list<array{user_id: int, amount: string, percentage: ?string}>
     */
    private function buildFixed(array $participantIds, array $fixedAmounts): array
    {
        $ids = collect($participantIds)->sort()->values()->all();

        return array_map(fn ($userId) => [
            'user_id' => $userId,
            'amount' => number_format((float) $fixedAmounts[$userId], 2, '.', ''),
            'percentage' => null,
        ], $ids);
    }
}
