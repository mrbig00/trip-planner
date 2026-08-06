<?php

namespace App\Support;

final class WebMercator
{
    /**
     * The pixel size of a single OSM tile image.
     */
    public const TILE_SIZE = 256;

    /**
     * Project a lat/lon pair to a Web Mercator pixel coordinate at the given
     * zoom level, in the same "world pixel space" used to address OSM tiles
     * (https://tile.openstreetmap.org/{z}/{x}/{y}.png).
     *
     * @return array{x: float, y: float}
     */
    public static function toWorldPixel(float $lat, float $lon, int $zoom): array
    {
        $tileCount = 2 ** $zoom;
        $latRad = deg2rad($lat);

        return [
            'x' => (($lon + 180) / 360) * $tileCount * self::TILE_SIZE,
            'y' => (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $tileCount * self::TILE_SIZE,
        ];
    }

    /**
     * The number of tiles per side at the given zoom level (2^zoom).
     */
    public static function tileCount(int $zoom): int
    {
        return 2 ** $zoom;
    }
}
