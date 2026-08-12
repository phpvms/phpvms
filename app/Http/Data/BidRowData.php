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
    ) {}

    /** @param array<string, int> $saved */
    public static function fromModel(Bid $bid, FlightDispatchPolicyData $policy, array $saved): self
    {
        $bid->loadMissing(['aircraft.airport', 'aircraft.subfleet', 'flight.airline', 'flight.arr_airport', 'flight.dpt_airport']);

        return new self(
            bid: BidData::fromModel($bid),
            flight: $bid->flight ? FlightListItemData::fromModel($bid->flight, $saved, $policy) : null,
            aircraft: $bid->aircraft ? EligibleAircraftData::fromModel($bid->aircraft) : null,
            state: 'confirmed',
            expiresAt: $policy->expireHours > 0
                ? $bid->created_at?->copy()->addHours($policy->expireHours)->toIso8601String()
                : null,
            canGenerateSimBrief: $policy->simbriefAvailable,
            canRemove: true,
        );
    }
}
