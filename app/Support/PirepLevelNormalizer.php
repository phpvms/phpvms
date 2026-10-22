<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Normalizes an incoming `pireps.level` value to a flight level.
 *
 * `level` is a flight level, not feet — resolved by reading the dominant
 * writer: vmsACARS serialises the field from a property literally named
 * `FlightLevel` ("The flight level entered", `PirepDto.cs:100-105`) and
 * keeps a separate `InitialAltitude` (a `Length`, `Pirep.cs:387-392`) for
 * the feet-valued initial altitude. See
 * openspec/changes/maplibre-map-platform/design.md, open question 6.
 *
 * Some clients still send feet — and so does at least one internal path:
 * `Pirep::fromSimBrief()` (`app/Models/Pirep.php:345`) pre-populates a
 * create-PIREP form from `$simbrief->ofp?->general->initial_altitude`,
 * which (like its sibling `SimBriefOfpNavlog::altitude_feet`) is in feet.
 * Direction was explicit: correct on ingest rather than reject the request.
 */
final class PirepLevelNormalizer
{
    /**
     * A flight level realistically tops out around 600 (FL600 ≈ 60,000 ft —
     * Concorde/U2 territory, far above anything phpVMS's typical fleet
     * flies), so a value materially above that cannot be a genuine flight
     * level. 1,000 sits comfortably clear of that ceiling on one side, and
     * clear of the low end too: even a short VFR hop cruises above 1,000 ft,
     * so no real PIREP's feet value falls at or below this threshold either.
     * Nothing plausible lands in the gap between "highest real flight
     * level" and "lowest real cruise altitude in feet".
     */
    private const int FLIGHT_LEVEL_CEILING = 1000;

    /**
     * Normalizes `$attrs['level']` in place if present and numeric. Attrs
     * without a `level` key, or with a null/non-numeric one (including
     * `''`), are returned unchanged — this is ingest normalization, not
     * validation, so a value that fails `numeric` validation elsewhere is
     * left for that check to reject, not silently coerced to 0.
     *
     * @param  array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    public static function normalize(array $attrs): array
    {
        if (!array_key_exists('level', $attrs) || !is_numeric($attrs['level'])) {
            return $attrs;
        }

        $raw = (float) $attrs['level'];

        if ($raw > self::FLIGHT_LEVEL_CEILING) {
            $normalized = self::coerce($raw);

            Log::warning('Pirep level looked like feet on ingest; normalized to a flight level', [
                'raw_level'        => $attrs['level'],
                'normalized_level' => $normalized,
            ]);

            $attrs['level'] = $normalized;

            return $attrs;
        }

        $attrs['level'] = self::coerce($raw);

        return $attrs;
    }

    /**
     * The coercion rule in isolation — pure, no I/O — so it's directly
     * testable without needing an attrs array or a log side effect.
     *
     * > FLIGHT_LEVEL_CEILING is treated as feet and divided by 100;
     * <= FLIGHT_LEVEL_CEILING is already a flight level and passes through
     * (only rounded to the nearest integer, matching the `level` column's
     * `integer` cast).
     */
    public static function coerce(float $level): int
    {
        return $level > self::FLIGHT_LEVEL_CEILING
            ? (int) round($level / 100)
            : (int) round($level);
    }
}
