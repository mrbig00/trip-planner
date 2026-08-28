<?php

namespace App\Actions\Expenses;

use App\Enums\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Looks up today's reference exchange rate between two currencies via the
 * free Frankfurter API (https://frankfurter.dev, sourced from the European
 * Central Bank) — used only to prefill the expense form's exchange_rate
 * input with a sensible starting point. The user can always override it,
 * and nothing here is ever trusted for the actual conversion math — that
 * always uses whatever rate ends up stored on the expense (see
 * Expense::convertToTripCurrencyCents()).
 *
 * Never throws: an unsupported pair (e.g. AED isn't part of the ECB
 * reference rates Frankfurter serves), a network failure, or a slow
 * response are all treated the same way — return null and leave the field
 * for the user to fill in by hand, exactly as if this lookup didn't exist.
 */
class FetchExchangeRate
{
    /**
     * How many units of $to one unit of $from is worth right now, or null if
     * unavailable. Cached for a few hours per pair — Frankfurter's rates only
     * update once a day, so there's no reason to hit it more often.
     */
    public function fetch(Currency $from, Currency $to): ?string
    {
        if ($from === $to) {
            return null;
        }

        return Cache::remember(
            "exchange-rate:{$from->value}:{$to->value}",
            now()->addHours(6),
            function () use ($from, $to) {
                try {
                    $response = Http::timeout(3)->get(config('services.frankfurter.url').'/latest', [
                        'base' => $from->value,
                        'symbols' => $to->value,
                    ]);
                } catch (\Throwable) {
                    return null;
                }

                if (! $response->successful()) {
                    return null;
                }

                $rate = $response->json("rates.{$to->value}");

                return is_numeric($rate) ? (string) $rate : null;
            }
        );
    }
}
