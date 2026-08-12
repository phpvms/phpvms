<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BidSelectionData extends Data
{
    public function __construct(
        public BidData $bid,
        public FlightDetailData $flight,
        public ?EligibleAircraftData $aircraft,
        public FlightDispatchPolicyData $policy,
        public string $state,
        public ?string $expiresAt,
        public bool $aircraftReserved,
    ) {}

    public static function fromModel(Bid $bid, FlightDispatchPolicyData $policy): self
    {
        $bid->loadMissing([
            'aircraft.airport',
            'aircraft.subfleet',
            'flight.airline',
            'flight.alt_airport',
            'flight.arr_airport',
            'flight.dpt_airport',
        ]);

        $expiresAt = $policy->expireHours > 0
            ? $bid->created_at?->copy()->addHours($policy->expireHours)->toIso8601String()
            : null;

        return new self(
            bid: BidData::fromModel($bid),
            flight: FlightDetailData::fromModel($bid->flight, [$bid->flight_id => $bid->id]),
            aircraft: $bid->aircraft ? EligibleAircraftData::fromModel($bid->aircraft) : null,
            policy: $policy,
            state: 'confirmed',
            expiresAt: $expiresAt,
            aircraftReserved: $policy->aircraftRequired && $bid->aircraft_id !== null,
        );
    }
}
