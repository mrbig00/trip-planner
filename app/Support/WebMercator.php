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

    /**
     * "Fit bounds": find the highest zoom level (most detail, capped at 17)
     * at which every given point's projected pixel position still fits
     * within a $width x $height canvas (minus $padding on each side),
     * mirroring the classic fitBounds() behavior of interactive map
     * libraries. Backs off one zoom level at a time down to a floor of 2
     * (the whole world in 4 tiles) if nothing smaller fits.
     *
     * The projection is recomputed at whichever zoom the loop actually
     * settles on — including the floor — before returning, so the returned
     * center is always at the same scale as the returned zoom; computing it
     * from a stale, previously-tried zoom's coordinates would put the tile
     * grid and the center at two different scales.
     *
     * @param  array<int, array{lat: float, lon: float}>  $points  must be non-empty
     * @return array{zoom: int, center: array{x: float, y: float}}
     */
    public static function fitBounds(array $points, int $width, int $height, int $padding = 40): array
    {
        $zoom = 17;

        while (true) {
            $projected = array_map(fn (array $point) => self::toWorldPixel($point['lat'], $point['lon'], $zoom), $points);
            $xs = array_column($projected, 'x');
            $ys = array_column($projected, 'y');
            $minX = min($xs);
            $maxX = max($xs);
            $minY = min($ys);
            $maxY = max($ys);

            $fits = ($maxX - $minX) <= ($width - $padding * 2) && ($maxY - $minY) <= ($height - $padding * 2);

            if ($fits || $zoom <= 2) {
                break;
            }

            $zoom--;
        }

        return [
            'zoom' => $zoom,
            'center' => ['x' => ($minX + $maxX) / 2, 'y' => ($minY + $maxY) / 2],
        ];
    }
}
