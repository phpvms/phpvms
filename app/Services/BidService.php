<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\AircraftState;
use App\Enums\AircraftStatus;
use App\Enums\BundleType;
use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Tour\TourService;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\Subfleet;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BidService extends Service
{
    public function __construct(
        private readonly FareService $fareSvc,
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
            'flight.simbrief' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            },
            'flight.simbrief.aircraft',
            'flight.simbrief.aircraft.subfleet',
        ];

        /** @var ?Bid $bid */
        $bid = Bid::with($with)->where(['id' => $bid_id])->first();
        if ($bid === null) {
            return null;
        }

        if ($bid->flight !== null) {
            if ($bid->aircraft !== null) {
                // Bid is for a specific aircraft — show only that aircraft's subfleet
                $bid->flight->setRelation(
                    'subfleets',
                    $bid->flight->subfleets()
                        ->where('subfleets.id', $bid->aircraft->subfleet_id)
                        ->with([
                            'fares',
                            'aircraft' => fn ($q) => $q->where('id', $bid->aircraft_id),
                        ])
                        ->get(),
                );
            } else {
                $bid->flight->setRelation(
                    'subfleets',
                    $bid->flight->accessibleSubfleetsFor($user, ['aircraft.bid', 'fares']),
                );
            }

            $this->fareSvc->getReconciledFaresForFlight($bid->flight);
        }

        return $bid;
    }

    /**
     * Find all of the bids for a given user, most recently briefed first.
     *
     * The pilot's own SimBrief for the bid's flight is the ordering key: a bid
     * is "current" because a briefing was just generated for it, not because it
     * was clicked on last. Bids with no briefing follow, newest bid first.
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
            'flight.simbrief' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            },
        ];

        $loadSubfleets = in_array('subfleets', $relations, true);

        if ($loadSubfleets) {
            // Eager-load filtered subfleets + their fares + aircraft via the
            // access-policy scope in a single query plan per relation.
            $with['flight'] = fn ($q) => $q->withAccessibleSubfleets($user);
        }

        if (in_array('simbrief_aircraft', $relations, true)) {
            $with = array_merge($with, [
                'flight.simbrief.aircraft',
                'flight.simbrief.aircraft.subfleet',
                'flight.simbrief.aircraft.subfleet.fares',
            ]);
        }

        // Newest bid first, tie-broken on the id. This is the base order the
        // briefing sort below falls back to, and on its own it is already
        // total: `bids`.`created_at` is a second-precision timestamp, so two
        // bids placed in the same second compare equal and would otherwise come
        // back in whatever order the driver returned. The id is auto-increment,
        // so a higher one is the later bid.
        $bids = Bid::with($with)
            ->where(['user_id' => $user->id])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($loadSubfleets) {
            foreach ($bids as $bid) {
                if ($bid->flight === null) {
                    continue;
                }

                // If the bid is for a specific aircraft, narrow the subfleet list
                // to that aircraft's subfleet only — preserves the historic UX of
                // showing only the booked aircraft on the bid card.
                if ($bid->aircraft !== null) {
                    $bid->flight->setRelation(
                        'subfleets',
                        $bid->flight->subfleets->where('id', $bid->aircraft->subfleet_id)->values(),
                    );
                }

                $this->fareSvc->getReconciledFaresForFlight($bid->flight);
            }
        }

        return $this->briefedFirst($bids);
    }

    /**
     * Order bids by how recently the pilot briefed them, newest first, leaving
     * bids with no briefing behind those that have one.
     *
     * Sorted here rather than in the query because ordering on the briefing
     * timestamp means ordering on a nullable correlated value, and the two
     * databases phpVMS supports disagree about where those nulls land: on a
     * DESC sort Postgres puts NULLs first and MySQL puts them last. Expressing
     * "nulls last" portably in SQL means a raw COALESCE with a driver-specific
     * cast, for a list that is one pilot's bids and already has its simbrief
     * eager-loaded.
     *
     * The sort is stable (PHP 8), so bids that are equal on briefing — which
     * includes every bid with no briefing at all — keep the query's newest-bid
     * -first order.
     *
     * @param  Collection<int, Bid> $bids
     * @return Collection<int, Bid>
     */
    private function briefedFirst(Collection $bids): Collection
    {
        return $bids->sort(function (Bid $a, Bid $b): int {
            $aAt = $a->flight?->simbrief?->created_at;
            $bAt = $b->flight?->simbrief?->created_at;

            // No briefing means no recency to compare, so it sorts after every
            // bid that has one instead of landing at whichever end the
            // comparison happened to put it.
            if ($aAt === null || $bAt === null) {
                return ($aAt === null ? 1 : 0) <=> ($bAt === null ? 1 : 0);
            }

            return $bAt <=> $aAt;
        })->values();
    }

    /**
     * Allow a user to bid on a flight. Check settings and all that good stuff
     *
     *
     * @throws BidExistsForAircraft
     * @throws BidExistsForFlight
     * @throws UserBidLimit
     * @throws ValidationException
     */
    public function addBid(Flight $flight, User $user, ?Aircraft $aircraft = null): Bid
    {
        // A tour is bid as a unit, so the whole chain is created at once and the
        // bid handed back is still this flight's. Resolved from the container
        // rather than injected: TourService depends on this service.
        if ($flight->bundle?->type === BundleType::Tour) {
            $bid = app(TourService::class)->start($flight, $user, $aircraft);

            $flight->refresh();

            return $this->getBid($user, $bid->id) ?? $bid;
        }

        $bid = DB::transaction(function () use ($flight, $user, $aircraft): Bid {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedFlight = Flight::query()->whereKey($flight->id)->lockForUpdate()->firstOrFail();
            $lockedAircraft = !$aircraft instanceof Aircraft
                ? null
                : Aircraft::query()->whereKey($aircraft->id)->lockForUpdate()->firstOrFail();

            $existing = Bid::query()
                ->where('user_id', $lockedUser->id)
                ->where('flight_id', $lockedFlight->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Bid) {
                if ($existing->aircraft_id === null && $lockedAircraft instanceof Aircraft) {
                    $this->assertAircraftMayBeBid($lockedFlight, $lockedUser, $lockedAircraft);
                    $existing->aircraft()->associate($lockedAircraft);
                    $existing->save();
                }

                $this->recomputeFlightBidState($lockedFlight);

                return $existing;
            }

            $this->assertFlightMayBeBid($lockedFlight, $lockedUser);

            if (!(bool) setting('bids.allow_multiple_bids', false)
                && Bid::query()->where('user_id', $lockedUser->id)->exists()) {
                throw new UserBidLimit($lockedUser);
            }

            if ((bool) setting('bids.disable_flight_on_bid', false)
                && Bid::query()->where('flight_id', $lockedFlight->id)->exists()) {
                throw new BidExistsForFlight($lockedFlight);
            }

            if ((bool) setting('bids.block_aircraft', false) && $lockedAircraft === null) {
                throw ValidationException::withMessages([
                    'aircraftId' => 'Select an eligible aircraft before placing this bid.',
                ]);
            }

            if ($lockedAircraft instanceof Aircraft) {
                $this->assertAircraftMayBeBid($lockedFlight, $lockedUser, $lockedAircraft);
            }

            $created = Bid::query()->create([
                'user_id'     => $lockedUser->id,
                'flight_id'   => $lockedFlight->id,
                'aircraft_id' => $lockedAircraft?->id,
            ]);

            $this->recomputeFlightBidState($lockedFlight);

            return $created;
        }, 3);

        $flight->refresh();

        return $this->getBid($user, $bid->id) ?? $bid;
    }

    /** @return Builder<Aircraft> */
    public function eligibleAircraftQuery(Flight $flight, User $user, ?int $subfleetId = null): Builder
    {
        $subfleetIds = $flight->accessibleSubfleetsFor($user)->pluck('id');

        return Aircraft::query()
            ->allowedFor($user, $flight)
            ->where('state', AircraftState::PARKED)
            ->where('status', AircraftStatus::ACTIVE)
            ->whereIn('subfleet_id', $subfleetIds)
            ->when(
                (bool) setting('simbrief.block_aircraft', false),
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

    /**
     * @return Collection<int, Subfleet>
     */
    public function configuredSubfleets(Flight $flight): Collection
    {
        return $flight->configuredSubfleets(['airline']);
    }

    /**
     * Remove a bid from a given flight
     */
    public function removeBid(Flight $flight, User $user): void
    {
        DB::transaction(function () use ($flight, $user): void {
            $lockedFlight = Flight::query()->whereKey($flight->id)->lockForUpdate()->first();
            if (!$lockedFlight instanceof Flight) {
                return;
            }

            Bid::query()
                ->where('flight_id', $lockedFlight->id)
                ->where('user_id', $user->id)
                ->delete();

            if ((bool) setting('simbrief.only_bids', false)) {
                SimBrief::query()
                    ->where('user_id', $user->id)
                    ->where('flight_id', $lockedFlight->id)
                    ->whereNull('pirep_id')
                    ->delete();
            }

            $this->recomputeFlightBidState($lockedFlight);
        });
    }

    public function removeBidById(int $bidId): void
    {
        $bid = Bid::query()->with(['flight', 'user'])->find($bidId);
        if ($bid?->flight && $bid->user) {
            $this->removeBid($bid->flight, $bid->user);
        }
    }

    public function removeBidsForFlight(Flight $flight): void
    {
        foreach (Bid::query()->where('flight_id', $flight->id)->with('user')->get() as $bid) {
            if ($bid->user) {
                $this->removeBid($flight, $bid->user);
            }
        }
    }

    public function removeBidsForUser(User $user): void
    {
        // The pilot is going away, so their live tours end rather than sitting
        // at in_progress with no bids left. This drops the tour bids too.
        app(TourService::class)->cancelLiveToursFor($user);

        foreach (Bid::query()->where('user_id', $user->id)->with('flight')->get() as $bid) {
            if ($bid->flight) {
                $this->removeBid($bid->flight, $user);
            }
        }
    }

    /**
     * If the setting is enabled, remove the bid
     *
     * @throws Exception
     */
    public function removeBidForPirep(Pirep $pirep): void
    {
        $flight = $pirep->flight;
        if (!$flight) {
            return;
        }

        Log::info('Removing bid for user: '.$pirep->user->ident.' on flight '.$flight->ident);
        $this->removeBid($flight, $pirep->user);
    }

    /**
     * Public so the tour path can run the identical guard against leg 1 rather
     * than reimplementing it. Not part of the ordinary bid API.
     */
    public function assertFlightMayBeBid(Flight $flight, User $user): void
    {
        if ((bool) setting('pilots.restrict_to_company', false)
            && $flight->airline_id !== $user->airline_id) {
            throw ValidationException::withMessages([
                'flightId' => 'This flight is not available to your company.',
            ]);
        }

        if ((bool) setting('pilots.only_flights_from_current', false)
            && $flight->dpt_airport_id !== $user->curr_airport_id) {
            throw ValidationException::withMessages([
                'flightId' => 'You must be at the departure airport to place this bid.',
            ]);
        }

    }

    /** Public for the same reason as assertFlightMayBeBid(). */
    public function assertAircraftMayBeBid(Flight $flight, User $user, Aircraft $aircraft): void
    {
        if ((bool) setting('bids.block_aircraft', false)
            && Bid::query()
                ->where('aircraft_id', $aircraft->id)
                ->where('user_id', '!=', $user->id)
                ->exists()) {
            throw new BidExistsForAircraft($aircraft);
        }

        if ($this->eligibleAircraftQuery($flight, $user)->whereKey($aircraft->id)->exists()) {
            return;
        }

        $reserved = (bool) setting('bids.block_aircraft', false)
            && Bid::query()
                ->where('aircraft_id', $aircraft->id)
                ->where('user_id', '!=', $user->id)
                ->exists();

        if ($reserved) {
            throw new BidExistsForAircraft($aircraft);
        }

        throw ValidationException::withMessages([
            'aircraftId' => 'This aircraft is no longer eligible. Refresh the aircraft list.',
        ]);
    }

    /** Public so the tour path can settle `has_bid` across all N legs it creates. */
    public function recomputeFlightBidState(Flight $flight): void
    {
        $hasBid = Bid::query()->where('flight_id', $flight->id)->exists();
        if ($flight->has_bid !== $hasBid) {
            $flight->has_bid = $hasBid;
            $flight->save();
        }
    }
}
