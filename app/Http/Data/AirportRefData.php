<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Minimal airport reference (icao + name) for SPA projections. */
#[TypeScript]
final class AirportRefData extends Data
{
    public function __construct(
        public string $icao,
        public string $name,
    ) {}

    public static function fromModel(?object $airport): ?self
    {
        return $airport ? new self(icao: $airport->icao, name: $airport->name) : null;
    }
}
