<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use App\Models\SimBrief;
use App\Models\User;
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
        public bool $ofpGenerated,
        public ?string $ofpPlanningUrl,
        public ?string $ofpUrl,
    ) {}

    public static function fromModel(Bid $bid, FlightDispatchPolicyData $policy, User $user): self
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
        $isOwner = $bid->user_id === $user->id;
        $ofpId = $isOwner
            ? SimBrief::query()
                ->where('user_id', $user->id)
                ->where('flight_id', $bid->flight_id)
                ->value('id')
            : null;

        return new self(
            bid: BidData::fromModel($bid),
            flight: FlightDetailData::fromModel($bid->flight, [$bid->flight_id => $bid->id]),
            aircraft: $bid->aircraft ? EligibleAircraftData::fromModel($bid->aircraft) : null,
            policy: $policy,
            state: 'confirmed',
            expiresAt: $expiresAt,
            aircraftReserved: $policy->aircraftRequired && $bid->aircraft_id !== null,
            ofpGenerated: $ofpId !== null,
            ofpPlanningUrl: $isOwner && $policy->simbriefEnabled && $ofpId === null
                ? route('frontend.ofp.planning', ['bid_id' => $bid->id])
                : null,
            ofpUrl: $ofpId === null ? null : route('frontend.ofp.briefing', $ofpId),
        );
    }
}
