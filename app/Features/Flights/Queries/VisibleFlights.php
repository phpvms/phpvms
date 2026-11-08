<?php

declare(strict_types=1);

namespace App\Features\Flights\Queries;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Flights a pilot may see and act on: visible, of an active airline, and of
 * the pilot's own airline when `pilots.restrict_to_company` is on.
 */
final class VisibleFlights
{
    /**
     * @return Builder<Flight>
     */
    public function for(User $user): Builder
    {
        $query = Flight::query()->visible()->whereHas('airline', fn (Builder $airline): Builder => $airline->where('active', true));

        if ((bool) setting('pilots.restrict_to_company', false)) {
            $query->where('airline_id', $user->airline_id);
        }

        return $query;
    }
}
