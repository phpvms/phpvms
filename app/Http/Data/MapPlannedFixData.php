<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One fix on the planned route. `altitudeFt` and `viaAirway` are null for
 * pireps archived before altitude retention, or for a navlog source that
 * never carried them — the renderer falls back to the payload's cruise level.
 */
#[TypeScript]
final class MapPlannedFixData extends Data
{
    public function __construct(
        public ?string $ident,
        public float $lat,
        public float $lon,
        public ?int $altitudeFt,
        public ?string $viaAirway,
        /** SimBrief's per-fix SID/STAR flag — groups the route into departure/enroute/arrival legs. */
        public bool $isSidStar = false,
    ) {}
}
