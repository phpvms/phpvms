<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One point on a flown route, flat and typed rather than GeoJSON — the
 * at-altitude renderer cannot consume GeoJSON and maplibre ignores GeoJSON
 * elevation (map-api spec, "Flat typed track points, not GeoJSON").
 */
#[TypeScript]
final class MapTrackPointData extends Data
{
    public function __construct(
        public float $lat,
        public float $lon,
        public ?float $altitude,
        public ?string $phase,
    ) {}
}
