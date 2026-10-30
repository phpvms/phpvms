<?php

declare(strict_types=1);

namespace App\Features\Flights\Queries;

use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Bid;
use App\Models\User;

/**
 * One flight as the flight page shows it: the detail DTO and the dispatch
 * policy, built as `FlightController::show()` builds them.
 */
final readonly class FlightDetail
{
    public function __construct(private VisibleFlights $flights) {}

    /**
     * @return array{flight: FlightDetailData, policy: FlightDispatchPolicyData}
     */
    public function handle(User $user, int|string $flightId): array
    {
        $flight = $this->flights->for($user)
            ->with(['airline', 'alt_airport', 'arr_airport', 'dpt_airport'])
            ->findOrFail($flightId);

        $bid = Bid::query()->where(['user_id' => $user->id, 'flight_id' => $flight->id])->first();
        $pilotAtLimit = !(bool) setting('bids.allow_multiple_bids', false)
            && Bid::query()->where('user_id', $user->id)->exists();
        $policy = FlightDispatchPolicyData::fromSettings($pilotAtLimit);

        return [
            'flight' => FlightDetailData::fromModel(
                $flight,
                $bid ? [$flight->id => $bid->id] : [],
                $policy,
                $pilotAtLimit,
                $policy->requireCurrentAirport && $flight->dpt_airport_id !== $user->curr_airport_id,
            ),
            'policy' => $policy,
        ];
    }
}
