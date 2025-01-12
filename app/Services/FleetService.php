<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\Typerating;

class FleetService extends Service
{
    /**
     * @return Subfleet
     */
    public function addSubfleetToRank(Subfleet $subfleet, Rank $rank, array $overrides = [])
    {
        $subfleet->ranks()->syncWithoutDetaching([$rank->id]);

        if ($overrides) {
            $subfleet->ranks()->updateExistingPivot($rank->id, $overrides);
        }

        $subfleet->save();
        $subfleet->refresh();

        return $subfleet;
    }

    public function removeSubfleetFromRank(Subfleet $subfleet, Rank $rank)
    {
        $subfleet->ranks()->detach($rank->id);
        $subfleet->save();
        $subfleet->refresh();

        return $subfleet;
    }

    /**
     * Add the subfleet to a type rating.
     */
    public function addSubfleetToTypeRating(Subfleet $subfleet, Typerating $typerating)
    {
        $subfleet->typeratings()->syncWithoutDetaching([$typerating->id]);
        $subfleet->save();
        $subfleet->refresh();

        return $subfleet;
    }

    /**
     * Remove the subfleet from a type rating.
     */
    public function removeSubfleetFromTypeRating(Subfleet $subfleet, Typerating $typerating)
    {
        $subfleet->typeratings()->detach($typerating->id);
        $subfleet->save();
        $subfleet->refresh();

        return $subfleet;
    }

    /**
     * Add the subfleet to a flight.
     */
    public function addSubfleetToFlight(Subfleet $subfleet, Flight $flight)
    {
        $flight->subfleets()->syncWithoutDetaching([$subfleet->id]);
        $subfleet->save();
        $subfleet->refresh();
    }

    /**
     * Remove the subfleet from a flight.
     */
    public function removeSubfleetFromFlight(Subfleet $subfleet, Flight $flight)
    {
        $flight->subfleets()->detach($subfleet->id);
    }
}
