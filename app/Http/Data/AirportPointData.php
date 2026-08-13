<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Airport reference with coords for the dashboard last-flight/route. */
#[TypeScript]
final class AirportPointData extends Data
{
    public function __construct(
        public string $id,
        public string $icao,
        public ?string $name,
        public ?float $lat,
        public ?float $lon,
    ) {}
}
