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
            $worldWidth = self::tileCount($zoom) * self::TILE_SIZE;
            $projected = array_map(fn (array $point) => self::toWorldPixel($point['lat'], $point['lon'], $zoom), $points);
            $ys = array_column($projected, 'y');
            $minY = min($ys);
            $maxY = max($ys);

            ['min' => $minX, 'max' => $maxX] = self::cyclicXBounds(array_column($projected, 'x'), $worldWidth);

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

    /**
     * Find the smallest span of x pixel coordinates that contains every
     * point, treating x as cyclic (it wraps at $worldWidth — the
     * antimeridian, where longitude 180° and -180° project to the same
     * place). A plain min/max would treat two points a few pixels apart
     * across the antimeridian (e.g. longitude 179.9° and -179.9°) as being
     * almost a full world apart, since one projects near 0 and the other
     * near $worldWidth.
     *
     * This instead finds the largest empty gap between consecutive points
     * around the circle and excludes it from the span — the points either
     * side of every *other* gap must be the true bounds. Whichever points
     * land before that gap (in sorted order) are then shifted a whole
     * $worldWidth forward, so the returned bounds are contiguous even when
     * they straddle the wrap point.
     *
     * @param  array<int, float>  $xs  must be non-empty, each in [0, $worldWidth)
     * @return array{min: float, max: float}
     */
    private static function cyclicXBounds(array $xs, float $worldWidth): array
    {
        sort($xs);
        $count = count($xs);

        $largestGap = -1.0;
        $largestGapIndex = $count - 1;

        for ($i = 0; $i < $count; $i++) {
            $next = $i === $count - 1 ? $xs[0] + $worldWidth : $xs[$i + 1];
            $gap = $next - $xs[$i];

            if ($gap > $largestGap) {
                $largestGap = $gap;
                $largestGapIndex = $i;
            }
        }

        // The wrap-around gap (last point to first, through the antimeridian)
        // is itself the largest — the points already sit in one contiguous
        // block with no wrapping needed.
        if ($largestGapIndex === $count - 1) {
            return ['min' => $xs[0], 'max' => $xs[$count - 1]];
        }

        return [
            'min' => $xs[$largestGapIndex + 1],
            'max' => $xs[$largestGapIndex] + $worldWidth,
        ];
    }
}
