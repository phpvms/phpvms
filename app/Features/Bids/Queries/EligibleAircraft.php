<?php

declare(strict_types=1);

namespace App\Features\Bids\Queries;

use App\Enums\AircraftState;
use App\Enums\AircraftStatus;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aircraft a pilot may bid with on a flight: parked, active, in a subfleet the
 * pilot may fly on that flight, and (with `simbrief.block_aircraft`) not held
 * by an unused SimBrief OFP.
 */
final class EligibleAircraft
{
    /**
     * @return Builder<Aircraft>
     */
    public function for(Flight $flight, User $user, ?int $subfleetId = null): Builder
    {
        $subfleetIds = $flight->accessibleSubfleetsFor($user)->pluck('id');

        return Aircraft::query()
            ->allowedFor($user, $flight)
            ->where('state', AircraftState::PARKED)
            ->where('status', AircraftStatus::ACTIVE)
            ->whereIn('subfleet_id', $subfleetIds)
            ->when(
                (bool) setting('operations.block_aircraft', false),
                fn (Builder $query): Builder => $query->whereDoesntHave(
                    'simbriefs',
                    fn (Builder $simbrief): Builder => $simbrief->whereNull('pirep_id'),
                ),
            )
            ->when($subfleetId !== null, fn (Builder $query): Builder => $query->where('subfleet_id', $subfleetId))
            ->with(['airport', 'subfleet'])
            ->orderBy('icao')
            ->orderBy('registration');
    }
}
