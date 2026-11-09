<?php

declare(strict_types=1);

namespace App\Features\Bids\Actions;

use App\Features\Bids\Queries\EligibleAircraft;
use App\Features\Flights\Queries\VisibleFlights;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Bid;
use App\Models\Subfleet;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything the bid (assignment) drawer needs for one flight: the flight, the
 * dispatch policy, the configured subfleets with their eligible-aircraft counts,
 * and the pilot's existing bid, if any.
 */
final readonly class LoadDispatch
{
    public function __construct(
        private VisibleFlights $flights,
        private EligibleAircraft $eligibleAircraft,
    ) {}

    /**
     * @return array{flight: FlightDetailData, policy: FlightDispatchPolicyData, subfleets: Collection<int, EligibleSubfleetData>, selection: ?BidSelectionData}
     */
    public function handle(User $user, int|string $flightId): array
    {
        $flight = $this->flights->for($user)
            ->with(['airline', 'alt_airport', 'arr_airport', 'dpt_airport'])
            ->findOrFail($flightId);

        $bid = Bid::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flight->id)
            ->first();

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
            'policy'    => $policy,
            'subfleets' => $flight->configuredSubfleets(['airline'])
                ->map(fn (Subfleet $subfleet): EligibleSubfleetData => EligibleSubfleetData::fromModel(
                    $subfleet,
                    $this->eligibleAircraft->for($flight, $user, $subfleet->id)->count(),
                ))
                ->values(),
            'selection' => $bid ? BidSelectionData::fromModel($bid, $policy, $user) : null,
        ];
    }
}
