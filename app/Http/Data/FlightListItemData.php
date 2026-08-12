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
        public ?string $scheduledDeparture,
        public ?string $scheduledArrival,
        public ?string $routeCode,
        public string $availability,
        public ?string $availabilityReason,
        public string $primaryAction,
    ) {}

    /**
     * @param array<string, int> $saved flight_id => bid_id
     */
    public static function fromModel(
        Flight $f,
        array $saved,
        ?FlightDispatchPolicyData $policy = null,
        bool $pilotAtLimit = false,
        bool $operationalLocationBlocked = false,
    ): self {
        $policy ??= FlightDispatchPolicyData::fromSettings();
        $hasOwnBid = isset($saved[$f->id]);
        $flightLocked = !$hasOwnBid && $policy->disableFlightOnBid && $f->has_bid;
        $unavailable = $flightLocked || (!$hasOwnBid && ($pilotAtLimit || $operationalLocationBlocked));

        return new self(
            id: $f->id,
            callsign: $f->ident,
            dpt: $f->dpt_airport?->icao,
            arr: $f->arr_airport?->icao,
            distanceNm: $f->distance ? (int) round($f->distance->toUnit('nmi')) : null,
            blockTime: $f->flight_time ? Time::minutesToTimeString((int) $f->flight_time) : null,
            type: $f->flight_type->getLabel(),
            airline: $f->airline ? new AirlineRefData(icao: $f->airline->icao, name: $f->airline->name) : null,
            bidId: $saved[$f->id] ?? null,
            scheduledDeparture: $f->dpt_time,
            scheduledArrival: $f->arr_time,
            routeCode: filled($f->route_code) ? $f->route_code : null,
            availability: $hasOwnBid ? 'bid' : ($unavailable ? 'locked' : 'available'),
            availabilityReason: $flightLocked
                ? 'Another pilot has a bid on this flight'
                : ((!$hasOwnBid && $pilotAtLimit)
                    ? 'Remove your current bid before selecting another flight'
                    : ((!$hasOwnBid && $operationalLocationBlocked)
                        ? 'You must be at the departure airport to bid on this flight'
                        : null)),
            primaryAction: $hasOwnBid ? 'overview' : ($unavailable ? 'unavailable' : 'bid'),
        );
    }
}
