<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A geo point on the nav-display route (icao + coords). */
#[TypeScript]
final class RoutePointData extends Data
{
    public function __construct(
        public string $icao,
        public ?string $name,
        public float $lat,
        public float $lon,
    ) {}
}
