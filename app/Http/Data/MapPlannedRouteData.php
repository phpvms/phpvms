<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The planned portion of a PIREP's route.
 *
 * `fallbackAltitudeFt` is what the renderer draws a flat planned route at
 * when `fixes` carry no per-fix altitude — deliberately NOT derived from
 * `pireps.level` (openspec/changes/maplibre-map-platform/design.md, open
 * question 6: the contract is a flight level, but ~93% of live
 * vmsACARS-sourced rows actually hold feet, with no way to tell which from
 * the column alone). Instead it's derived from the flight's own recorded
 * `altitude_msl` telemetry, which has a real, known unit. Already in feet,
 * not a flight level — no `* 100` needed downstream, unlike the `level`
 * value this replaced.
 */
#[TypeScript]
final class MapPlannedRouteData extends Data
{
    /**
     * @param list<MapPlannedFixData> $fixes
     */
    public function __construct(
        public array $fixes,
        public ?int $fallbackAltitudeFt,
    ) {}
}
