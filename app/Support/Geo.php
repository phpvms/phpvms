<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical great-circle geometry helpers (nautical miles).
 *
 * Mirrors `resources/js/admin/routeforge/lib/geo.ts` byte-for-byte (same
 * Earth radius, same haversine arithmetic) so client-side preview distances
 * and server-side `/preview-airports` decoration stay consistent. Keep the
 * two implementations in sync by reference: if you change the formula here,
 * change `geo.ts` to match (and vice versa).
 */
final class Geo
{
    /** Earth radius in nautical miles used by the haversine formula. */
    public const float EARTH_RADIUS_NM = 3440.065;

    /**
     * Great-circle distance in nautical miles between two lat/lon points.
     *
     * Latitudes and longitudes are decimal degrees. Identical endpoints
     * return `0.0`. Caller is responsible for filtering coordinates that
     * are unknown (`null` lat / lon) before invoking — this helper assumes
     * both points are well-defined.
     */
    public static function haversineNm(float $latA, float $lonA, float $latB, float $lonB): float
    {
        $phi1 = deg2rad($latA);
        $phi2 = deg2rad($latB);
        $dPhi = deg2rad($latB - $latA);
        $dLambda = deg2rad($lonB - $lonA);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        $c = 2 * asin(min(1.0, sqrt($a)));

        return self::EARTH_RADIUS_NM * $c;
    }
}
