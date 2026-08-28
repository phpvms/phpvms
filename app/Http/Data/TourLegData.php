<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Flight;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One leg row inside a TourListItemData: enough to draw the chain
 * (leg number, ident, route) and mark the pilot's progress through it.
 */
#[TypeScript]
final class TourLegData extends Data
{
    public function __construct(
        public string $flightId,
        public ?int $routeLeg,
        public string $ident,
        public string $dpt,
        public string $arr,
        public bool $flown,
    ) {}

    /** @param array<string, bool> $flownFlightIds */
    public static function fromModel(Flight $flight, array $flownFlightIds): self
    {
        return new self(
            flightId: $flight->id,
            routeLeg: $flight->route_leg,
            ident: ($flight->airline->code ?? '').$flight->flight_number,
            dpt: $flight->dpt_airport_id,
            arr: $flight->arr_airport_id,
            flown: isset($flownFlightIds[$flight->id]),
        );
    }
}
