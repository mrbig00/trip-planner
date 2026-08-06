<?php

namespace App\Support;

final class Money
{
    /**
     * Convert a decimal amount (e.g. "12.34") to integer cents, without float rounding.
     */
    public static function toCents(string $decimalAmount): int
    {
        return (int) bcmul($decimalAmount, '100', 0);
    }

    /**
     * Convert integer cents back to a decimal string (e.g. "12.34").
     */
    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
