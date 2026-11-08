<?php

declare(strict_types=1);

namespace App\Features\Bids\Queries;

use App\Http\Data\BidRowData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Bid;
use App\Models\User;

/**
 * A pilot's bids as the My bids page lists them: expiring first (expiry is
 * created_at + bids.expire_time, so the oldest bid runs out next), with the
 * flight, aircraft and tour each row needs already loaded.
 */
final class PilotBids
{
    /**
     * @return list<BidRowData>
     */
    public function for(User $user): array
    {
        $bids = Bid::query()
            ->where('user_id', $user->id)
            ->with([
                'aircraft.airport',
                'aircraft.subfleet',
                'flight.airline',
                'flight.arr_airport',
                'flight.dpt_airport',
                // Whether a briefing exists decides View vs Generate OFP; scoped to this pilot.
                'flight.simbrief' => fn ($simbrief) => $simbrief->where('user_id', $user->id),
                'userTour',
            ])
            ->get();

        $valid = collect();
        $saved = [];

        foreach ($bids as $bid) {
            // A bid whose flight is gone is dropped, as the controller does.
            if (!$bid->flight) {
                $bid->delete();

                continue;
            }

            $valid->add($bid);
            $saved[$bid->flight_id] = $bid->id;
        }

        $policy = FlightDispatchPolicyData::fromSettings(
            !(bool) setting('bids.allow_multiple_bids', false) && $valid->isNotEmpty(),
        );

        return $valid
            ->sortBy(fn (Bid $bid): int => $bid->created_at?->getTimestamp() ?? 0)
            ->map(fn (Bid $bid): BidRowData => BidRowData::fromModel($bid, $policy, $saved))
            ->values()
            ->all();
    }
}
