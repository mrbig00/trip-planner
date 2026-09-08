<?php

declare(strict_types=1);

namespace App\Support;

final class TripPalette
{
    /**
     * Number of distinct hues in the atlas palette. Trips beyond this count
     * cycle back to slot 1 — see resources/css/app.css's --color-trip-*
     * tokens.
     */
    public const SLOTS = 6;

    /**
     * Deterministically map a trip id to one of the atlas palette's color
     * slots (1-indexed, matching the --color-trip-* CSS custom properties),
     * so the same trip always renders in the same color on the global
     * Explore map — no color column to store or keep in sync.
     */
    public static function slotFor(int $tripId): int
    {
        return ($tripId % self::SLOTS) + 1;
    }
}
