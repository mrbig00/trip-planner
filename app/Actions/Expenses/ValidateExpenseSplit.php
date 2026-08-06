<?php

namespace App\Actions\Expenses;

use App\Enums\ExpenseSplitType;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class ValidateExpenseSplit
{
    /**
     * Percentages are user-entered with 2 decimal places, so allow a small
     * tolerance around 100% before rejecting the split as unbalanced.
     */
    private const PERCENTAGE_TOLERANCE = 0.5;

    /**
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $percentages  keyed by user id
     * @param  array<int, string>  $fixedAmounts  keyed by user id
     */
    public function validate(ExpenseSplitType $splitType, array $participantIds, array $percentages, array $fixedAmounts, int $totalCents): void
    {
        if (empty($participantIds)) {
            $this->fail('participant_ids', 'Select at least one participant to split this expense with.');
        }

        match ($splitType) {
            ExpenseSplitType::Equal => null,
            ExpenseSplitType::Percentage => $this->validatePercentages($participantIds, $percentages),
            ExpenseSplitType::Fixed => $this->validateFixedAmounts($participantIds, $fixedAmounts, $totalCents),
        };
    }

    /**
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $percentages
     */
    private function validatePercentages(array $participantIds, array $percentages): void
    {
        $sum = 0.0;

        foreach ($participantIds as $userId) {
            if (! isset($percentages[$userId]) || ! is_numeric($percentages[$userId])) {
                $this->fail('percentages', 'Enter a percentage for every selected participant.');
            }

            if ((float) $percentages[$userId] < 0) {
                $this->fail('percentages', 'Enter a non-negative percentage for every selected participant.');
            }

            $sum += (float) $percentages[$userId];
        }

        if (abs($sum - 100) > self::PERCENTAGE_TOLERANCE) {
            $this->fail('percentages', 'Percentages must sum to 100%.');
        }
    }

    /**
     * @param  array<int, int>  $participantIds
     * @param  array<int, string>  $fixedAmounts
     */
    private function validateFixedAmounts(array $participantIds, array $fixedAmounts, int $totalCents): void
    {
        $sumCents = 0;

        foreach ($participantIds as $userId) {
            if (! isset($fixedAmounts[$userId]) || ! is_numeric($fixedAmounts[$userId]) || (float) $fixedAmounts[$userId] < 0) {
                $this->fail('fixed_amounts', 'Enter a non-negative amount for every selected participant.');
            }

            $sumCents += Money::toCents((string) $fixedAmounts[$userId]);
        }

        if ($sumCents !== $totalCents) {
            $this->fail('fixed_amounts', 'Fixed amounts must sum exactly to the expense total ('.Money::fromCents($totalCents).').');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
