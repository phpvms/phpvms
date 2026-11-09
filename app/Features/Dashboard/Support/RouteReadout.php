<?php

declare(strict_types=1);

namespace App\Features\Dashboard\Support;

/**
 * The nav display readout: initial great-circle bearing and distance between
 * two points, as skylight's geo helpers compute them.
 */
final class RouteReadout
{
    private const float EARTH_NM = 3440.065;

    /**
     * Bearing in whole degrees (0 to 359).
     */
    public static function bearing(float $fromLat, float $fromLon, float $toLat, float $toLon): int
    {
        $la1 = deg2rad($fromLat);
        $la2 = deg2rad($toLat);
        $dl = deg2rad($toLon - $fromLon);

        $degrees = rad2deg(atan2(
            sin($dl) * cos($la2),
            cos($la1) * sin($la2) - sin($la1) * cos($la2) * cos($dl),
        ));

        return ((int) round($degrees) + 360) % 360;
    }

    /**
     * Distance in whole nautical miles.
     */
    public static function distanceNm(float $fromLat, float $fromLon, float $toLat, float $toLon): int
    {
        $la1 = deg2rad($fromLat);
        $la2 = deg2rad($toLat);
        $dla = deg2rad($toLat - $fromLat);
        $dlo = deg2rad($toLon - $fromLon);

        $h = sin($dla / 2) ** 2 + cos($la1) * cos($la2) * sin($dlo / 2) ** 2;

        return (int) round(2 * self::EARTH_NM * asin(min(1.0, sqrt($h))));
    }
}
