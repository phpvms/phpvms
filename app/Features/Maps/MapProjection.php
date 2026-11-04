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

    /** Moves a longitude east of the antimeridian on maps that cross it. */
    public static function normalizeLon(MapData $map, float $lon): float
    {
        if ($map->lonShift !== 0 && $lon < $map->lonWrap) {
            return $lon + $map->lonShift;
        }

        return $lon;
    }
}
