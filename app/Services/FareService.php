<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\Subfleet;
use App\Support\Math;
use Exception;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FareService extends Service
{
    /**
     * Save the list of fares, reconcile the proper pricing and save it to the PIREP.
     * Get the fares that have been filled out for the PIREP, and then get the fares for
     * the flight and subfleet. Then merge them together, and return the final list of:
     *
     *      count       = number of pax
     *      price       = how much each pax unit paid
     *      capacity    = max number of pax units
     *
     * If count > capacity, count will be adjusted to capacity
     *
     * @param PirepFare[] $fares
     *
     * @throws Exception
     */
    public function saveToPirep(Pirep $pirep, array $fares)
    {
        if ($fares === []) {
            return;
        }

        // Remove all the previous fares
        PirepFare::where('pirep_id', $pirep->id)->delete();

        $fares = collect($fares);
        Log::info('Finance: PIREP: '.$pirep->id.', flight fares: ', $fares->toArray());

        // Read the original fare and get this information from it
        $all_fares = $this->getAllFares($pirep->flight, $pirep->aircraft->subfleet);
        $all_fares->map(function ($fare, $_) use ($fares, $pirep) {
            /**
             * See if there's match with the provided fares, so we can copy the information over
             *
             * @var PirepFare $pirep_fare
             */
            $pirep_fare = $fares->where('fare_id', $fare->id)->first();

            if (!$pirep_fare) {
                Log::info('Finance: PIREP: '.$pirep->id.', original fare not found', $fare->toArray());

                return;
            }

            Log::info('Finance: PIREP: '.$pirep->id.', fare count: '.$pirep_fare);

            $pirep_fare->pirep_id = $pirep->id;
            $pirep_fare->code = $fare->code;
            $pirep_fare->name = $fare->name;
            $pirep_fare->type = $fare->type;

            // Only copy over fields which don't already have values

            if (empty($pirep_fare->capacity)) {
                $pirep_fare->capacity = $fare->capacity;
            }

            if (empty($pirep_fare->count)) {
                // If the count is greater than capacity, set it to the smaller amount
                $pirep_fare->count = min($pirep_fare->count, $pirep_fare->capacity);
            }

            if (empty($pirep_fare->price)) {
                $pirep_fare->price = $fare->price;
            }

            if (empty($pirep_fare->cost)) {
                $pirep_fare->cost = $fare->cost;
            }

            $pirep_fare->fare_id = null; // Remove the index to it
        });

        // Save all of the fares; they might have been modded above, or from the caller
        // (e.g, if it was edited on the admin page)
        foreach ($fares as $fare) {
            $fare->save();
        }
    }

    /**
     * Determine the correct fares to use between a subfleet and flight. You probably aren't
     * looking to call this one directly, but instead, call getReconciledFaresForFlight()
     *
     * @param  ?Collection<int, Fare> $subfleet_fares The fare for a subfleet
     * @param  ?Collection<int, Fare> $flight_fares   The fares on a flight
     * @return Collection<int, Fare>  Collection of Fare
     */
    public function getFareWithOverrides(?Collection $subfleet_fares, ?Collection $flight_fares): Collection
    {
        /**
         * Make sure we've got something in terms of fares on the subfleet or the flight
         */
        if ((!$subfleet_fares || $subfleet_fares->isEmpty()) && (!$flight_fares || $flight_fares->isEmpty())) {
            return collect();
        }

        /**
         * Check to see if there are any subfleet fares. This might only have fares on the
         * flight, no matter how rare that might be
         */
        if (!$subfleet_fares instanceof \Illuminate\Support\Collection || count($subfleet_fares) === 0) {
            return $flight_fares->map(function ($fare, $_) {
                return $this->getFareWithPivot($fare, $fare->pivot);
            });
        }

        return $subfleet_fares->map(function ($sf_fare, $_) use ($flight_fares) {
            /**
             * Get the fare, using the subfleet's pivot values. This will return
             * the fares with all the costs, etc, that are overridden for the given subfleet
             */
            $fare = $this->getFareWithPivot($sf_fare, $sf_fare->pivot);

            /**
             * Now, using the fares that have already been used from the subfleet
             * now pass those fares in for the flight to override.
             *
             * First look to see that there actually is an override for that fare that's on
             * the flight
             */
            $flight_fare = $flight_fares->whereStrict('id', $fare->id)->first();
            if ($flight_fare === null) {
                return $fare;
            }

            /**
             * Found an override on the flight for the given fare. Check to see if we
             * have values there that can be used to override or act as a pivot
             */
            $fare = $this->getFareWithPivot($fare, $flight_fare->pivot);

            /**
             * Finally return the fare that we have, it should have gone through the
             * multiple levels of reconciliation that were required
             */
            return $fare;
        });
    }

    /**
     * This will return the flight but all of the subfleets will have the corrected fares with the
     * right amounts based on the pivots, and with the correct "inheritence" for the flights
     */
    public function getReconciledFaresForFlight(Flight $flight): Flight
    {
        $subfleets = $flight->subfleets;
        $flight_fares = $flight->fares;

        /**
         * @var int      $key
         * @var Subfleet $subfleet
         */
        foreach ($subfleets as $key => $subfleet) {
            // @phpstan-ignore-next-line
            $subfleet->fares = $this->getFareWithOverrides($subfleet->fares, $flight_fares);
        }

        // @phpstan-ignore-next-line
        $flight->subfleets = $subfleets;

        return $flight;
    }

    /**
     * Get the fares for a particular flight, with the subfleet that is in use being passed in
     *
     * @param  Flight|null   $flight
     * @param  Subfleet|null $subfleet
     * @return Collection
     */
    public function getAllFares($flight, $subfleet)
    {
        $flight_fares = $flight ? $flight->fares : collect();

        if (empty($subfleet)) {
            throw new InvalidArgumentException('Subfleet argument missing');
        }

        return $this->getFareWithOverrides($subfleet->fares, $flight_fares);
    }

    /**
     * Get a fare with the proper prices/costs populated in the pivot
     *
     *
     * @return mixed
     */
    public function getFares($fare)
    {
        return $this->getFareWithPivot($fare, $fare->pivot);
    }

    /**
     * Get the correct price of something supplied with the correct pivot
     */
    public function getFareWithPivot(Fare $fare, Pivot $pivot): Fare
    {
        if (filled($pivot->price)) {
            if (strpos($pivot->price, '%', -1) !== false) {
                $fare->price = Math::getPercent($fare->price, $pivot->price);
            } else {
                $fare->price = $pivot->price;
            }
        }

        if (filled($pivot->cost)) {
            if (strpos($pivot->cost, '%', -1) !== false) {
                $fare->cost = Math::getPercent($fare->cost, $pivot->cost);
            } else {
                $fare->cost = $pivot->cost;
            }
        }

        if (filled($pivot->capacity)) {
            if (strpos($pivot->capacity, '%', -1) !== false) {
                $fare->capacity = (int) floor(Math::getPercent($fare->capacity, $pivot->capacity));
            } else {
                $fare->capacity = (int) floor($pivot->capacity);
            }
        }

        // $fare->notes = '';
        $fare->active = true;

        return $fare;
    }

    /**
     * Return all the fares for an aircraft. check the pivot
     * table to see if the price/cost/capacity has been overridden
     * and return the correct amounts.
     *
     *
     * @return Collection
     */
    public function getForSubfleet(Subfleet $subfleet)
    {
        $fares = $subfleet->fares->map(function ($fare) {
            return $this->getFares($fare);
        });

        return $fares;
    }

    /**
     * Attach a fare to an flight
     */
    public function setForFlight(Flight $flight, Fare $fare, array $override = []): Flight
    {
        Log::info('Setting fare "'.$fare->name.'" to flight "'.$flight->ident.'"');

        $flight->fares()->syncWithoutDetaching([$fare->id]);

        // modify any pivot values?
        if ($override !== []) {
            $flight->fares()->updateExistingPivot($fare->id, $override);
        }

        $flight->save();
        $flight->refresh();

        return $flight;
    }

    /**
     * @return Flight
     */
    public function delFareFromFlight(Flight $flight, Fare $fare)
    {
        Log::info('Removing fare "'.$fare->name.'" to flight "'.$flight->ident.'"');

        $flight->fares()->detach($fare->id);
        $flight->refresh();

        return $flight;
    }

    /**
     * Attach a fare to a subfleet
     */
    public function setForSubfleet(Subfleet $subfleet, Fare $fare, array $override = []): Subfleet
    {
        Log::info('Setting fare "'.$fare->name.'" to subfleet "'.$subfleet->name.'"');

        $subfleet->fares()->syncWithoutDetaching([$fare->id]);

        // modify any pivot values?
        if ($override !== []) {
            $subfleet->fares()->updateExistingPivot($fare->id, $override);
        }

        $subfleet->save();
        $subfleet->refresh();

        return $subfleet;
    }

    /**
     * Delete the fare from a subfleet
     *
     *
     * @return Subfleet|null|static
     */
    public function delFareFromSubfleet(Subfleet &$subfleet, Fare &$fare)
    {
        Log::info('Removing fare "'.$fare->name.'" from subfleet "'.$subfleet->name.'"');

        $subfleet->fares()->detach($fare->id);
        $subfleet->refresh();

        return $subfleet;
    }

    /**
     * Get the fares for a PIREP, this just returns the PirepFare
     * model which includes the counts for that particular fare
     *
     *
     * @return Collection
     */
    public function getForPirep(Pirep $pirep)
    {
        return PirepFare::where('pirep_id', $pirep->id)->get();
    }
}
