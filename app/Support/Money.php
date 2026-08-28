<?php

namespace App\Support;

use App\Enums\Currency;

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

    /**
     * Format integer cents with a currency's symbol, e.g. "$12.34".
     */
    public static function format(int $cents, Currency|string $currency): string
    {
        $currency = $currency instanceof Currency ? $currency : Currency::from($currency);

        return $currency->symbol().number_format($cents / 100, 2);
    }

    /**
     * Format a decimal amount (e.g. "12.34") with a currency's symbol.
     */
    public static function formatDecimal(string $decimalAmount, Currency|string $currency): string
    {
        return self::format(self::toCents($decimalAmount), $currency);
    }
}
