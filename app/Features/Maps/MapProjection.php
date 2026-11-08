<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * Places lat/lon on a map's viewBox. Mirrors the maths in bin/maps/build.mjs:
 * x = (lon' - lonMin) * cosLat0 * k, y = (latMax - lat) * k.
 */
final class MapProjection
{
    /**
     * @return array{x: float, y: float}
     */
    public static function point(MapData $map, float $lat, float $lon): array
    {
        return [
            'x' => (self::normalizeLon($map, $lon) - $map->lonMin) * $map->cosLat0 * $map->k,
            'y' => ($map->latMax - $lat) * $map->k,
        ];
    }

    /**
     * The great circle between two points, as runs of viewBox points. A run
     * ends where the line wraps across the map's edge, so it is never drawn
     * across the whole map.
     *
     * @return list<list<array{x: float, y: float}>>
     */
    public static function greatCircle(MapData $map, float $lat1, float $lon1, float $lat2, float $lon2, int $steps = 64): array
    {
        [$phi1, $lambda1, $phi2, $lambda2] = array_map(deg2rad(...), [$lat1, $lon1, $lat2, $lon2]);
        $angle = 2 * asin(sqrt(
            sin(($phi2 - $phi1) / 2) ** 2 + cos($phi1) * cos($phi2) * sin(($lambda2 - $lambda1) / 2) ** 2,
        ));

        $runs = [[]];
        $previous = null;

        for ($i = 0; $i <= $steps; $i++) {
            $f = $i / $steps;

            if ($angle < 1e-9) {
                [$lat, $lon] = [$lat1, $lon1];
            } else {
                $a = sin((1 - $f) * $angle) / sin($angle);
                $b = sin($f * $angle) / sin($angle);
                $x = $a * cos($phi1) * cos($lambda1) + $b * cos($phi2) * cos($lambda2);
                $y = $a * cos($phi1) * sin($lambda1) + $b * cos($phi2) * sin($lambda2);
                $z = $a * sin($phi1) + $b * sin($phi2);
                $lat = rad2deg(atan2($z, sqrt($x ** 2 + $y ** 2)));
                $lon = rad2deg(atan2($y, $x));
            }

            $point = self::point($map, $lat, $lon);

            if ($previous !== null && abs($point['x'] - $previous['x']) > $map->width / 2) {
                $runs[] = [];
            }

            $runs[array_key_last($runs)][] = $point;
            $previous = $point;
        }

        return array_values(array_filter($runs, fn (array $run): bool => count($run) > 1));
    }

    /** Moves a longitude east of the antimeridian on maps that cross it. */
    public static function normalizeLon(MapData $map, float $lon): float
    {
        if ($map->lonShift !== 0 && $lon < $map->lonWrap) {
            return $lon + $map->lonShift;
        }

        return $lon;
    }
}
