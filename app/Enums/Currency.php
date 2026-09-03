<?php

namespace App\Enums;

/**
 * A curated set of common/travel-relevant ISO 4217 currency codes — not the
 * full ISO-4217 list. Extend by adding a case + a symbol() match arm.
 *
 * No intl/NumberFormatter dependency on purpose: neither Dockerfile nor
 * production.Dockerfile installs the intl extension, and nothing else in the
 * app does locale-aware formatting. symbol() is a hand-rolled, hardcoded
 * lookup so money formatting stays framework-free, matching App\Support\Money's
 * existing pure-bcmath/number_format style.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case HUF = 'HUF';
    case RON = 'RON';
    case CHF = 'CHF';
    case JPY = 'JPY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case CZK = 'CZK';
    case PLN = 'PLN';
    case SEK = 'SEK';
    case NOK = 'NOK';
    case DKK = 'DKK';
    case TRY = 'TRY';
    case THB = 'THB';
    case MXN = 'MXN';
    case CNY = 'CNY';
    case INR = 'INR';
    case AED = 'AED';
    case BRL = 'BRL';

    public static function default(): self
    {
        return self::USD;
    }

    /**
     * Symbol/abbreviation shown before an amount (e.g. "$12.34"). Always a
     * prefix, never a suffix — a deliberate simplification (some currencies
     * are conventionally suffixed) to keep formatting a single, predictable
     * rule across the whole app.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::HUF => 'Ft',
            self::RON => 'lei',
            self::CHF => 'CHF',
            self::JPY => '¥',
            self::CAD => 'CA$',
            self::AUD => 'A$',
            self::CZK => 'Kč',
            self::PLN => 'zł',
            self::SEK => 'kr',
            self::NOK => 'kr',
            self::DKK => 'kr',
            self::TRY => '₺',
            self::THB => '฿',
            self::MXN => 'MX$',
            self::CNY => '¥',
            self::INR => '₹',
            self::AED => 'AED',
            self::BRL => 'R$',
        };
    }

    /**
     * "USD ($)" style label for <flux:select> options.
     */
    public function label(): string
    {
        return "{$this->value} ({$this->symbol()})";
    }
}
