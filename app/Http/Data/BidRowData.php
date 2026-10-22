<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One My Bids table row for the SPA: a bid + its flight summary.
 *
 * Shape matches the per-row `context` the page's `bids.row.actions` slot draws
 * (`{ bid, flight }`), so addon slot components keep resolving `@bid` / `@flight`.
 * `flight` is nullable defensively (the controller prunes orphan bids).
 */
#[TypeScript]
final class BidRowData extends Data
{
    public function __construct(
        public BidData $bid,
        public ?FlightListItemData $flight,
        public ?EligibleAircraftData $aircraft,
        public string $state,
        public ?string $expiresAt,
        public bool $canGenerateSimBrief,
        public bool $canRemove,
        /** The tour run this bid is a leg of, for the row's Tour badge. */
        public ?string $tourName,
        /** The tour's bundle id, for linking to its overview page. */
        public ?int $tourId,
        /** This leg's position in the tour, when it is one. */
        public ?int $tourLeg,
        /**
         * A briefing already generated for this flight, so the card offers
         * "View OFP" instead of "Generate OFP". Null when none exists.
         */
        public ?string $ofpUrl,
    ) {}

    /** @param array<string, int> $saved */
    public static function fromModel(Bid $bid, FlightDispatchPolicyData $policy, array $saved): self
    {
        $bid->loadMissing(['aircraft.airport', 'aircraft.subfleet', 'flight.airline', 'flight.arr_airport', 'flight.dpt_airport', 'flight.simbrief', 'userTour']);

        return new self(
            bid: BidData::fromModel($bid),
            flight: $bid->flight ? FlightListItemData::fromModel($bid->flight, $saved, $policy) : null,
            aircraft: $bid->aircraft ? EligibleAircraftData::fromModel($bid->aircraft) : null,
            state: 'confirmed',
            expiresAt: $policy->expireHours > 0
                ? $bid->created_at?->copy()->addHours($policy->expireHours)->toIso8601String()
                : null,
            canGenerateSimBrief: $policy->simbriefEnabled,
            canRemove: true,
            tourName: $bid->userTour?->name,
            tourId: $bid->userTour?->bundle_id,
            tourLeg: $bid->flight?->route_leg,
            ofpUrl: $bid->flight?->simbrief
                ? route('frontend.ofp.briefing', $bid->flight->simbrief->id)
                : null,
        );
    }
}
