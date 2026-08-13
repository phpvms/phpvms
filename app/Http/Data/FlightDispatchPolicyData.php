<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class FlightDispatchPolicyData extends Data
{
    public function __construct(
        public bool $aircraftRequired,
        public bool $chooseLaterAllowed,
        public bool $allowMultipleBids,
        public bool $pilotBidLimitReached,
        public bool $disableFlightOnBid,
        public int $expireHours,
        public bool $restrictToCompany,
        public bool $discoveryCurrentAirportOnly,
        public bool $requireCurrentAirport,
        public bool $restrictAircraftToRank,
        public bool $restrictAircraftToTypeRating,
        public bool $aircraftAtDepartureOnly,
        public bool $companyAircraftOnly,
        public bool $simbriefEnabled,
        public bool $simbriefRequiresBid,
        public bool $simbriefBlocksAircraft,
    ) {}

    public static function fromSettings(bool $pilotBidLimitReached = false): self
    {
        return new self(
            aircraftRequired: (bool) setting('bids.block_aircraft', false),
            chooseLaterAllowed: !(bool) setting('bids.block_aircraft', false),
            allowMultipleBids: (bool) setting('bids.allow_multiple_bids', false),
            pilotBidLimitReached: $pilotBidLimitReached,
            disableFlightOnBid: (bool) setting('bids.disable_flight_on_bid', false),
            expireHours: max(0, (int) setting('bids.expire_time', 0)),
            restrictToCompany: (bool) setting('pilots.restrict_to_company', false),
            discoveryCurrentAirportOnly: (bool) setting('pilots.only_show_flights_from_current', false),
            requireCurrentAirport: (bool) setting('pilots.only_flights_from_current', false),
            restrictAircraftToRank: (bool) setting('pireps.restrict_aircraft_to_rank', true),
            restrictAircraftToTypeRating: (bool) setting('pireps.restrict_aircraft_to_typerating', false),
            aircraftAtDepartureOnly: (bool) setting('pireps.only_aircraft_at_dpt_airport', false),
            companyAircraftOnly: (bool) setting('flights.only_company_aircraft', false),
            simbriefEnabled: filled(setting('simbrief.api_key')),
            simbriefRequiresBid: (bool) setting('simbrief.only_bids', false),
            simbriefBlocksAircraft: (bool) setting('simbrief.block_aircraft', false),
        );
    }
}
