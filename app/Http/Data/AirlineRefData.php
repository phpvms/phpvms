<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Minimal airline reference (icao + name) for SPA list/detail projections. */
#[TypeScript]
final class AirlineRefData extends Data
{
    public function __construct(
        public string $icao,
        public string $name,
    ) {}
}
