<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The airports for a single-flight map detail payload, with coordinates. */
#[TypeScript]
final class MapDetailAirportsData extends Data
{
    public function __construct(
        public ?AirportPointData $dpt,
        public ?AirportPointData $arr,
        public ?AirportPointData $alt,
    ) {}
}
