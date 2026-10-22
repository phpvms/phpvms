<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reduces a flown track's point count before it's serialised for the map API
 * (map-api spec, "Flown tracks are simplified before serialisation" / D15).
 *
 * Three-dimensional Douglas-Peucker: altitude is folded in as a third axis
 * alongside a local metre-scale projection of lat/lon, so a point kept only
 * because of a climb or descent (not a change in ground track) still survives
 * simplification and the climb/cruise/descent profile is preserved.
 *
 * Points are `array{lat: float, lon: float, altitude: ?float, phase: ?string}`
 * — the same shape `MapController` builds from `acars` rows — so this class
 * has no dependency on the Data/DTO layer and stays independently testable.
 */
final class MapTrackSimplifier
{
    /** Mean Earth radius in metres, for the local equirectangular projection. */
    private const float EARTH_RADIUS_M = 6_371_000.0;

    private const float FEET_TO_METRES = 0.3048;

    /**
     * Recursive Douglas-Peucker is worst-case O(n²) when almost every point
     * is kept — an adversarial zigzag track (every point alternating far
     * above and below the line) hits that worst case, not a real flight.
     * 5,000 such points take several seconds of CPU; this budget caps total
     * point-to-line comparisons across the whole recursion so one request
     * can't be made to burn unbounded CPU regardless of `MapController`'s
     * row cap (map-api spec, "simplification does not recurse without
     * limit"). Comfortably above what any real track needs — a well-behaved
     * long-haul track's total comparisons land in the low thousands.
     */
    private const int MAX_COMPARISONS = 200_000;

    private int $comparisons = 0;

    public function __construct(
        /** Perpendicular-distance tolerance, in metres, below which a point is dropped. */
        private readonly float $toleranceMeters = 250.0,
    ) {}

    /**
     * @param  list<array{lat: float, lon: float, altitude: ?float, phase: ?string}> $points
     * @return list<array{lat: float, lon: float, altitude: ?float, phase: ?string}>
     */
    public function simplify(array $points): array
    {
        $count = count($points);

        // Too short to simplify — returned as-is per the spec, never emptied.
        if ($count < 3) {
            return $points;
        }

        $keep = array_fill(0, $count, false);
        $keep[0] = true;
        $keep[$count - 1] = true;

        $this->comparisons = 0;
        $this->reduce($points, 0, $count - 1, $keep);

        $result = [];
        foreach ($keep as $index => $isKept) {
            if ($isKept) {
                $result[] = $points[$index];
            }
        }

        return $result;
    }

    /**
     * @param list<array{lat: float, lon: float, altitude: ?float, phase: ?string}> $points
     * @param array<int, bool>                                                      $keep
     */
    private function reduce(array $points, int $start, int $end, array &$keep): void
    {
        if ($end <= $start + 1) {
            return;
        }

        // Budget exhausted: stop subdividing. Whatever `$keep` holds so far
        // stands — every point in this range that hasn't been individually
        // ruled out is kept, which is a safe (if less aggressively
        // simplified) fallback, never a crash or an empty result.
        if ($this->comparisons >= self::MAX_COMPARISONS) {
            for ($i = $start + 1; $i < $end; $i++) {
                $keep[$i] = true;
            }

            return;
        }

        $maxDistance = 0.0;
        $splitIndex = -1;

        for ($i = $start + 1; $i < $end; $i++) {
            $this->comparisons++;
            $distance = $this->perpendicularDistanceMeters($points[$i], $points[$start], $points[$end]);

            if ($distance > $maxDistance) {
                $maxDistance = $distance;
                $splitIndex = $i;
            }

            if ($this->comparisons >= self::MAX_COMPARISONS) {
                break;
            }
        }

        if ($splitIndex === -1 || $maxDistance <= $this->toleranceMeters) {
            return;
        }

        $keep[$splitIndex] = true;

        $this->reduce($points, $start, $splitIndex, $keep);
        $this->reduce($points, $splitIndex, $end, $keep);
    }

    /**
     * Perpendicular distance, in metres, from $point to the segment
     * $lineStart-$lineEnd, in a local projection anchored at $lineStart with
     * altitude as the third axis.
     *
     * Altitude only enters the comparison when $point, $lineStart, and
     * $lineEnd all carry a recorded value. Defaulting a missing altitude to
     * 0 (ground level) would read as a vertical excursion against real
     * cruise-altitude neighbours and get the point wrongly retained — so a
     * missing altitude instead falls back to a purely horizontal (lat/lon)
     * comparison for this segment (map-api spec, "Missing altitude is not a
     * deviation").
     *
     * 1 m of vertical deviation is deliberately weighed the same as 1 m of
     * lateral deviation — the point of doing this in 3D rather than 2D+a
     * separate altitude threshold is one unified distance that represents
     * true 3D positional error, matching the shared substrate's technique
     * (design.md D2).
     *
     * @param array{lat: float, lon: float, altitude: ?float, phase: ?string} $point
     * @param array{lat: float, lon: float, altitude: ?float, phase: ?string} $lineStart
     * @param array{lat: float, lon: float, altitude: ?float, phase: ?string} $lineEnd
     */
    private function perpendicularDistanceMeters(array $point, array $lineStart, array $lineEnd): float
    {
        [$px, $py, $pz] = $this->toLocalMeters($point, $lineStart);
        [$ex, $ey, $ez] = $this->toLocalMeters($lineEnd, $lineStart);

        $verticalUsable = $point['altitude'] !== null
            && $lineStart['altitude'] !== null
            && $lineEnd['altitude'] !== null;

        $lineLengthSquared = $verticalUsable
            ? $ex ** 2 + $ey ** 2 + $ez ** 2
            : $ex ** 2 + $ey ** 2;

        // Degenerate (zero-length) segment: distance is just to the shared point.
        if ($lineLengthSquared < 1e-9) {
            return $verticalUsable
                ? sqrt($px ** 2 + $py ** 2 + $pz ** 2)
                : sqrt($px ** 2 + $py ** 2);
        }

        // Project the point onto the segment, clamped to its ends.
        $dot = $verticalUsable
            ? $px * $ex + $py * $ey + $pz * $ez
            : $px * $ex + $py * $ey;
        $t = max(0.0, min(1.0, $dot / $lineLengthSquared));

        $closestX = $t * $ex;
        $closestY = $t * $ey;

        if (!$verticalUsable) {
            return sqrt(($px - $closestX) ** 2 + ($py - $closestY) ** 2);
        }

        $closestZ = $t * $ez;

        return sqrt(($px - $closestX) ** 2 + ($py - $closestY) ** 2 + ($pz - $closestZ) ** 2);
    }

    /**
     * Local equirectangular projection of $point around $origin: x/y in
     * metres (accurate over the short spans a Douglas-Peucker pass compares),
     * z as altitude in metres. z is 0.0 and unused whenever either altitude
     * is null — callers gate on that via the $verticalUsable check above
     * rather than trusting this placeholder.
     *
     * @param  array{lat: float, lon: float, altitude: ?float, phase: ?string} $point
     * @param  array{lat: float, lon: float, altitude: ?float, phase: ?string} $origin
     * @return array{0: float, 1: float, 2: float}
     */
    private function toLocalMeters(array $point, array $origin): array
    {
        $originLatRad = deg2rad($origin['lat']);

        $x = deg2rad($point['lon'] - $origin['lon']) * cos($originLatRad) * self::EARTH_RADIUS_M;
        $y = deg2rad($point['lat'] - $origin['lat']) * self::EARTH_RADIUS_M;
        $z = ($point['altitude'] !== null && $origin['altitude'] !== null)
            ? ($point['altitude'] - $origin['altitude']) * self::FEET_TO_METRES
            : 0.0;

        return [$x, $y, $z];
    }
}
