<?php

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BidService extends Service
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly FlightService $flightSvc
    ) {}

    /**
     * Get a specific bid for a user
     */
    public function getBid(User $user, $bid_id): ?Bid
    {
        $with = [
            'aircraft',
            'aircraft.subfleet',
            'flight',
            'flight.fares',
            'flight.simbrief' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            },
            'flight.simbrief.aircraft',
            'flight.simbrief.aircraft.subfleet',
            'flight.subfleets',
            'flight.subfleets.aircraft',
            'flight.subfleets.aircraft.bid',
            'flight.subfleets.fares',
        ];

        /** @var Bid $bid */
        $bid = Bid::with($with)->where(['id' => $bid_id])->first();
        if ($bid === null) {
            return null;
        }

        // Reconcile the aircraft for this bid
        // TODO: Only do this if there isn't a Simbrief attached?
        if (!empty($bid->aircraft)) {
            $bid->flight->subfleets = $this->flightSvc->getSubfleetsForBid($bid);
        } else {
            $bid->flight = $this->flightSvc->filterSubfleets($user, $bid->flight);
        }

        $bid->flight = $this->fareSvc->getReconciledFaresForFlight($bid->flight);

        return $bid;
    }

    /**
     * Find all of the bids for a given user
     *
     *
     * @return Bid[]
     */
    public function findBidsForUser(User $user, array $relations = ['subfleets', 'simbrief_aircraft']): Collection|array|null
    {
        $with = [
            'aircraft',
            'flight',
            'flight.airline',
            'flight.fares',
            'flight.field_values',
            'flight.simbrief' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            },
        ];

        foreach ($relations as $relation) {
            $with = array_merge($with, match ($relation) {
                'subfleets' => [
                    'flight.subfleets',
                    'flight.subfleets.aircraft',
                    'flight.subfleets.aircraft.bid',
                    'flight.subfleets.fares',
                ],
                'simbrief_aircraft' => [
                    'flight.simbrief.aircraft',
                    'flight.simbrief.aircraft.subfleet',
                    'flight.simbrief.aircraft.subfleet.fares',
                ],
                default => [],
            });
        }

        $bids = Bid::with($with)->where(['user_id' => $user->id])->get();

        if (in_array('subfleets', $relations, true)) {
            foreach ($bids as $bid) {
                if ($bid->aircraft) {
                    $bid->flight->subfleets = $this->flightSvc->getSubfleetsForBid($bid);
                } else {
                    $bid->flight = $this->flightSvc->filterSubfleets($user, $bid->flight);
                }

                $bid->flight = $this->fareSvc->getReconciledFaresForFlight($bid->flight);
            }
        }

        return $bids;
    }

    /**
     * Allow a user to bid on a flight. Check settings and all that good stuff
     *
     *
     * @return mixed
     *
     * @throws \App\Exceptions\BidExistsForFlight
     */
    public function addBid(Flight $flight, User $user, ?Aircraft $aircraft = null)
    {
        // Get all of the bids for this user. See if they're allowed to have multiple
        // bids
        $bid_count = Bid::where(['user_id' => $user->id])->count();
        if ($bid_count > 0 && setting('bids.allow_multiple_bids') === false) {
            throw new UserBidLimit($user);
        }

        if (setting('bids.block_aircraft') && $aircraft) {
            $ac_bid_count = Bid::where(['aircraft_id' => $aircraft->id])->count();
            if ($ac_bid_count > 0) {
                throw new BidExistsForAircraft($aircraft);
            }
        }

        // Get all of the bids for this flight
        $bids = Bid::where(['flight_id' => $flight->id])->get();
        if ($bids->count() > 0) {
            // Does the flight have a bid set?
            if ($flight->has_bid === false) {
                $flight->has_bid = true;
                $flight->save();
            }
            // Check all the bids for one of this user
            foreach ($bids as $bid) {
                if ($bid->user_id === $user->id) {
                    Log::info('Bid exists, user='.$user->ident.', flight='.$flight->id);

                    return $bid;
                }
            }
            // Check if the flight should be blocked off
            if (setting('bids.disable_flight_on_bid') === true) {
                throw new BidExistsForFlight($flight);
            }
            // This is already controlled above at line 114 with user bid count,
            // To prevent or allow multiple bids. Should not be here at all
            if (setting('bids.allow_multiple_bids') === false) {
                // throw new BidExistsForFlight($flight);
            }
        } elseif ($flight->has_bid === true) {
            /* @noinspection NestedPositiveIfStatementsInspection */
            Log::info('Bid exists, flight='.$flight->id.'; no entry in bids table, cleaning up');
        }

        if (setting('bids.block_aircraft') && $aircraft) {
            $bid = Bid::firstOrCreate([
                'user_id'     => $user->id,
                'flight_id'   => $flight->id,
                'aircraft_id' => $aircraft->id,
            ]);
        } else {
            $bid = Bid::firstOrCreate([
                'user_id'   => $user->id,
                'flight_id' => $flight->id,
            ]);
        }

        $flight->has_bid = true;
        $flight->save();

        return $this->getBid($user, $bid->id);
    }

    /**
     * Remove a bid from a given flight
     */
    public function removeBid(Flight $flight, User $user)
    {
        $bids = Bid::where([
            'flight_id' => $flight->id,
            'user_id'   => $user->id,
        ])->get();

        foreach ($bids as $bid) {
            $bid->forceDelete();
        }

        // Remove SimBrief OFP when removing the bid if it is not flown
        if (setting('simbrief.only_bids')) {
            $simbrief = SimBrief::where([
                'user_id'   => $user->id,
                'flight_id' => $flight->id,
            ])->whereNull('pirep_id')->delete();
        }

        // Only flip the flag if there are no bids left for this flight
        $bid_count = Bid::where(['flight_id' => $flight->id])->count();
        if ($bid_count === 0) {
            $flight->has_bid = false;
            $flight->save();
        }
    }

    /**
     * If the setting is enabled, remove the bid
     *
     *
     * @throws \Exception
     */
    public function removeBidForPirep(Pirep $pirep)
    {
        $flight = $pirep->flight;
        if (!$flight) {
            return;
        }

        $bid = Bid::where([
            'user_id'   => $pirep->user->id,
            'flight_id' => $flight->id,
        ]);

        if ($bid) {
            Log::info('Bid for user: '.$pirep->user->ident.' on flight '.$flight->ident);
            $bid->delete();
        }
    }
}
