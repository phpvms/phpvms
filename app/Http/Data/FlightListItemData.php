<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Flight;
use App\Support\Units\Time;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of one flight for the schedule page. SPA-only; the Blade
 * path keeps the Eloquent model. Mirrors the retired FlightsPresenter row.
 */
#[TypeScript]
final class FlightListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $callsign,
        public ?string $dpt,
        public ?string $arr,
        public ?int $distanceNm,
        public ?string $blockTime,
        public ?string $type,
        public ?AirlineRefData $airline,
        public ?int $bidId,
    ) {}

    /**
     * @param array<string, int> $saved flight_id => bid_id
     */
    public static function fromModel(Flight $f, array $saved): self
    {
        return new self(
            id: $f->id,
            callsign: $f->ident,
            dpt: $f->dpt_airport?->icao,
            arr: $f->arr_airport?->icao,
            distanceNm: $f->distance ? (int) round($f->distance->toUnit('nmi')) : null,
            blockTime: $f->flight_time ? Time::minutesToTimeString((int) $f->flight_time) : null,
            type: $f->flight_type?->getLabel(),
            airline: $f->airline ? new AirlineRefData(icao: $f->airline->icao, name: $f->airline->name) : null,
            bidId: $saved[$f->id] ?? null,
        );
    }
}
