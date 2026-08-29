<?php

declare(strict_types=1);

namespace App\Features\Tour;

use App\Contracts\Service;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The whole lifecycle of one pilot's run through a tour bundle: start, advance,
 * cancel, expire.
 *
 * The service owns tour state only. Bid mechanics stay in BidService — this
 * calls back into it for the leg-1 guards, the `has_bid` recompute and every
 * bid removal, rather than reimplementing any of them.
 */
class TourService extends Service
{
    public function __construct(
        private readonly BidService $bidSvc,
    ) {}

    /**
     * Bid a whole tour: every leg, one aircraft, one `user_tours` row.
     *
     * `$anyLeg` is whichever flight the pilot actually clicked. The tour always
     * starts at leg 1 regardless, and the bid handed back is the one for the
     * flight they named — BidService::addBid() is typed `: Bid` and its callers
     * expect that flight's bid.
     *
     * @throws ValidationException
     */
    public function start(Flight $anyLeg, User $user, ?Aircraft $aircraft = null): Bid
    {
        $bundle = $anyLeg->bundle;
        if ($bundle === null) {
            throw ValidationException::withMessages([
                'flightId' => 'This tour has no legs and cannot be bid.',
            ]);
        }

        $sequence = $bundle->tourLegSequence();

        if (!$sequence['valid']) {
            throw ValidationException::withMessages([
                'flightId' => match ($sequence['problem']) {
                    'missing'   => 'This tour is missing leg '.$sequence['leg'].' and cannot be bid.',
                    'duplicate' => 'This tour has more than one leg '.$sequence['leg'].' and cannot be bid.',
                    default     => 'This tour has no legs and cannot be bid.',
                },
            ]);
        }

        /** @var Collection<int, Flight> $legs */
        $legs = $sequence['flights'];
        $firstLeg = $legs->first();

        return DB::transaction(function () use ($anyLeg, $user, $aircraft, $bundle, $legs, $firstLeg): Bid {
            // Same three locks in the same order as BidService::addBid(), taken
            // before any consistent read. `bids` has no unique index on
            // aircraft_id, so the aircraft lock is the only thing stopping two
            // pilots reserving one airframe; the user lock is what makes a
            // double-submitted start return the existing tour instead of dying
            // on bids_user_flight_unique.
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedFirstLeg = Flight::query()->whereKey($firstLeg->id)->lockForUpdate()->firstOrFail();
            $lockedAircraft = !$aircraft instanceof Aircraft
                ? null
                : Aircraft::query()->whereKey($aircraft->id)->lockForUpdate()->firstOrFail();

            $existing = UserTour::query()
                ->where('user_id', $lockedUser->id)
                ->where('bundle_id', $bundle->id)
                ->where('status', TourStatus::InProgress)
                ->first();

            if ($existing instanceof UserTour) {
                return $this->bidFor($lockedUser, $anyLeg);
            }

            // Behind the user lock, so two concurrent starts serialize and
            // cannot both slip under the limit. 0 means unlimited.
            $maxInProgress = (int) setting('tours.max_in_progress', 1);
            if ($maxInProgress > 0) {
                $inProgress = UserTour::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('status', TourStatus::InProgress)
                    ->count();

                if ($inProgress >= $maxInProgress) {
                    throw ValidationException::withMessages([
                        'flightId' => $maxInProgress === 1
                            ? 'You already have a tour in progress. Finish or cancel it before starting another.'
                            : "You can have at most {$maxInProgress} tours in progress.",
                    ]);
                }
            }

            // Leg 1 runs the full gauntlet; legs 2..N cannot be checked at all
            // at this point (they depart elsewhere, and the aircraft is not
            // PARKED there), so they are inserted unchecked on purpose.
            $this->bidSvc->assertFlightMayBeBid($lockedFirstLeg, $lockedUser);

            if (!(bool) setting('bids.allow_multiple_bids', false)
                && Bid::query()->where('user_id', $lockedUser->id)->exists()) {
                throw new UserBidLimit($lockedUser);
            }

            if ((bool) setting('bids.disable_flight_on_bid', false)
                && Bid::query()->where('flight_id', $lockedFirstLeg->id)->exists()) {
                throw new BidExistsForFlight($lockedFirstLeg);
            }

            if ((bool) setting('bids.block_aircraft', false) && $lockedAircraft === null) {
                throw ValidationException::withMessages([
                    'aircraftId' => 'Select an eligible aircraft before starting this tour.',
                ]);
            }

            if ($lockedAircraft instanceof Aircraft) {
                $this->bidSvc->assertAircraftMayBeBid($lockedFirstLeg, $lockedUser, $lockedAircraft);
            }

            $tour = UserTour::query()->create([
                'user_id'        => $lockedUser->id,
                'bundle_id'      => $bundle->id,
                'aircraft_id'    => $lockedAircraft?->id,
                'pirep_id'       => null,
                'flight_id'      => $firstLeg->id,
                'name'           => $bundle->name,
                'description'    => $bundle->description,
                'status'         => TourStatus::InProgress,
                'legs_total'     => $legs->count(),
                'legs_completed' => 0,
                'legs'           => $legs->map(fn (Flight $leg): array => [
                    'flight_id' => $leg->id,
                    'route_leg' => $leg->route_leg,
                    'pirep_id'  => null,
                    'filed_at'  => null,
                ])->values()->all(),
                // Always set explicitly: the column is nullable in the database
                // for a MySQL timestamp reason, not because a tour may lack one.
                'started_at' => Carbon::now('UTC'),
            ]);

            foreach ($legs as $leg) {
                Bid::query()->updateOrCreate(
                    ['user_id' => $lockedUser->id, 'flight_id' => $leg->id],
                    ['aircraft_id' => $lockedAircraft?->id, 'user_tour_id' => $tour->id],
                );

                $this->bidSvc->recomputeFlightBidState($leg);
            }

            return $this->bidFor($lockedUser, $anyLeg);
        }, 3);
    }

    /**
     * Record a filed leg against the pilot's running tour.
     *
     * Called after PirepService::handleDiversion() on purpose: a diversion nulls
     * the PIREP's `flight_id`, which drops it out of tour tracking entirely, and
     * the PirepDiverted listener has already cancelled the run by then.
     */
    public function advance(Pirep $pirep): void
    {
        if ($pirep->flight_id === null) {
            return;
        }

        $tour = $this->liveTourForFlight($pirep->user_id, $pirep->flight_id);
        if (!$tour instanceof UserTour) {
            return;
        }

        $legs = $tour->legs ?? [];
        $matched = false;

        foreach ($legs as $index => $leg) {
            if (($leg['flight_id'] ?? null) === $pirep->flight_id) {
                $legs[$index]['pirep_id'] = $pirep->id;
                $legs[$index]['filed_at'] = ($pirep->submitted_at ?? Carbon::now('UTC'))->toIso8601String();
                $matched = true;
            }
        }

        if (!$matched) {
            return;
        }

        // Derived, never incremented: legs can be filed out of order, and
        // PirepService::submit() hands back the prior PIREP on a re-file, so a
        // counter would double-count one and mark a tour complete with a leg
        // still unflown.
        $completed = 0;
        foreach ($legs as $leg) {
            if (($leg['pirep_id'] ?? null) === null) {
                break;
            }

            $completed++;
        }

        $everyLegFiled = collect($legs)->every(fn (array $leg): bool => ($leg['pirep_id'] ?? null) !== null);

        $tour->legs = $legs;
        $tour->legs_completed = $completed;
        $tour->pirep_id = $pirep->id;

        if ($everyLegFiled) {
            $tour->status = TourStatus::Completed;
            $tour->completed_at = Carbon::now('UTC');
            $tour->flight_id = null;
        } else {
            $tour->flight_id = $legs[$completed]['flight_id'] ?? null;
        }

        $tour->save();
    }

    /**
     * Point the tour at the PIREP the pilot has just opened, so the column is
     * populated while they are airborne rather than only after they file.
     */
    public function attachPirep(Pirep $pirep): void
    {
        if ($pirep->flight_id === null) {
            return;
        }

        $tour = $this->liveTourForFlight($pirep->user_id, $pirep->flight_id);
        $tour?->update(['pirep_id' => $pirep->id]);
    }

    /** The pilot gave up, was removed, or a leg failed to complete cleanly. */
    public function cancel(UserTour $tour): void
    {
        $this->finish($tour, TourStatus::Cancelled);
    }

    /** `bids.expire_time` swept the run up — nobody chose this one. */
    public function expire(UserTour $tour): void
    {
        $this->finish($tour, TourStatus::Expired);
    }

    /**
     * Cancel the run this PIREP's leg belongs to. A terminal tour is left alone:
     * rejecting a PIREP from a tour that already completed changes nothing.
     */
    public function cancelForPirep(Pirep $pirep): void
    {
        if ($pirep->flight_id === null) {
            return;
        }

        $this->cancelForFlight($pirep->user_id, $pirep->flight_id);
    }

    /**
     * Same, by flight, for the caller that no longer has the PIREP's flight_id:
     * a diversion nulls it before anyone downstream can read it.
     */
    public function cancelForFlight(?int $userId, string $flightId): void
    {
        $tour = $this->liveTourForFlight($userId, $flightId);
        if ($tour instanceof UserTour) {
            $this->cancel($tour);
        }
    }

    /** Every live run this pilot holds, ended — used when the pilot is removed. */
    public function cancelLiveToursFor(User $user): void
    {
        UserTour::query()
            ->where('user_id', $user->id)
            ->where('status', TourStatus::InProgress)
            ->get()
            ->each(fn (UserTour $tour) => $this->cancel($tour));
    }

    /** The live tour holding `$flightId` in its roster, if the pilot is on one. */
    public function liveTourForFlight(?int $userId, string $flightId): ?UserTour
    {
        if ($userId === null) {
            return null;
        }

        // Reads the (user_id, status) index; a pilot holds at most a handful of
        // live runs, so the roster match happens in PHP rather than in JSON SQL.
        return UserTour::query()
            ->where('user_id', $userId)
            ->where('status', TourStatus::InProgress)
            ->get()
            ->first(fn (UserTour $tour): bool => collect($tour->legs ?? [])
                ->contains(fn (array $leg): bool => ($leg['flight_id'] ?? null) === $flightId));
    }

    /**
     * Drop what is left of a run and stamp its outcome. The row itself is kept,
     * progress and all — awards and pilot history read it later.
     */
    private function finish(UserTour $tour, TourStatus $status): void
    {
        if ($tour->status !== TourStatus::InProgress) {
            return;
        }

        DB::transaction(function () use ($tour, $status): void {
            $bids = Bid::query()->where('user_tour_id', $tour->id)->with(['flight', 'user'])->get();

            foreach ($bids as $bid) {
                if ($bid->flight instanceof Flight && $bid->user instanceof User) {
                    $this->bidSvc->removeBid($bid->flight, $bid->user);
                }
            }

            // A bid whose flight no longer resolves can't go through removeBid(),
            // and left behind it would hold an aircraft forever.
            Bid::query()->where('user_tour_id', $tour->id)->delete();

            $tour->status = $status;
            $tour->save();
        });
    }

    /**
     * The bid for the flight the caller named. Absent only when that leg has
     * already been flown and its bid consumed.
     *
     * @throws ValidationException
     */
    private function bidFor(User $user, Flight $flight): Bid
    {
        $bid = Bid::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flight->id)
            ->first();

        if ($bid instanceof Bid) {
            return $bid;
        }

        throw ValidationException::withMessages([
            'flightId' => 'You have already flown this leg of the tour.',
        ]);
    }
}
