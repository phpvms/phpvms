<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Flight;
use App\Support\Units\Time;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat, JSON-safe flight summary for the SPA (one row of the My Bids table).
 *
 * The public typed properties ARE the SPA contract: `#[TypeScript]` opts this
 * class into `php artisan typescript:transform`, which generates the matching
 * type into resources/js/apps/fe/apps/spa/types/generated.d.ts. The SPA reads that
 * generated type instead of hand-mirroring the shape.
 *
 * This is the SPA projection ONLY. The legacy Blade path keeps receiving the
 * Eloquent Flight model (relations, value objects, in-template queries), so this
 * DTO deliberately flattens — e.g. `distanceNm` is a plain int, while Blade still
 * gets the `distance` value object.
 */
#[TypeScript]
final class FlightData extends Data
{
    public function __construct(
        public string $id,
        public string $callsign,
        public ?string $dpt,
        public ?string $arr,
        public ?int $distanceNm,
        public ?string $blockTime,
        public ?string $type,
    ) {}

    /**
     * Build from an eager-loaded Flight. Mirrors the retired BidsPresenter
     * projection. Reads `airline` (via Flight::ident) + `flight_type`; the
     * controller eager-loads those, so this never lazy-loads.
     */
    public static function fromModel(Flight $flight): self
    {
        return new self(
            id: $flight->id,
            callsign: $flight->ident,
            dpt: $flight->dpt_airport_id,
            arr: $flight->arr_airport_id,
            distanceNm: $flight->distance ? (int) round($flight->distance->toUnit('nmi')) : null,
            blockTime: $flight->flight_time ? Time::minutesToTimeString((int) $flight->flight_time) : null,
            type: $flight->flight_type?->getLabel(),
        );
    }
}
