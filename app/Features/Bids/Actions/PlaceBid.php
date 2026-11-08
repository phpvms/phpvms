<?php

declare(strict_types=1);

namespace App\Features\Bids\Actions;

use App\Enums\BundleType;
use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Hooks\Exceptions\HookDenied;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\PlacingBid;
use App\Features\Tour\TourService;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Place a bid for a pilot on a flight, optionally with an aircraft.
 *
 * The one place a bid is created, for the web, the API and Livewire, so the
 * `PlacingBid` gate cannot be bypassed. A tour flight starts the whole tour.
 * Repeating the same bid returns the existing one (and fills in an aircraft if
 * the bid had none).
 */
final readonly class PlaceBid
{
    public function __construct(
        private BidService $bids,
        private TourService $tours,
    ) {}

    /**
     * @throws HookDenied           when an addon gate refuses the bid
     * @throws UserBidLimit
     * @throws BidExistsForFlight
     * @throws BidExistsForAircraft
     * @throws ValidationException  when an aircraft is required, or not eligible
     */
    public function handle(Flight $flight, User $user, ?Aircraft $aircraft = null): Bid
    {
        $startsTour = $flight->bundle?->type === BundleType::Tour;

        // The gate guards bid creation only. A repeat for a bid the pilot already
        // holds (e.g. attaching an aircraft from OFP planning) is not a new bid,
        // so an addon rule such as "one bid at a time" must not refuse it.
        $holdsBid = Bid::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flight->id)
            ->exists();

        // Outside the row locks below, so addon code never holds them.
        if (!$holdsBid) {
            Hooks::gate(new PlacingBid($user, $flight, $aircraft, $startsTour));
        }

        // A tour is bid as a unit, so the whole chain is created at once and the
        // bid handed back is still this flight's.
        if ($startsTour) {
            $bid = $this->tours->start($flight, $user, $aircraft);

            $flight->refresh();

            return $this->bids->getBid($user, $bid->id) ?? $bid;
        }

        $bid = DB::transaction(function () use ($flight, $user, $aircraft): Bid {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedFlight = Flight::query()->whereKey($flight->id)->lockForUpdate()->firstOrFail();
            $lockedAircraft = $aircraft instanceof Aircraft
                ? Aircraft::query()->whereKey($aircraft->id)->lockForUpdate()->firstOrFail()
                : null;

            $existing = Bid::query()
                ->where('user_id', $lockedUser->id)
                ->where('flight_id', $lockedFlight->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Bid) {
                if ($existing->aircraft_id === null && $lockedAircraft instanceof Aircraft) {
                    $this->bids->assertAircraftMayBeBid($lockedFlight, $lockedUser, $lockedAircraft);
                    $existing->aircraft()->associate($lockedAircraft);
                    $existing->save();
                }

                $this->bids->recomputeFlightBidState($lockedFlight);

                return $existing;
            }

            $this->bids->assertFlightMayBeBid($lockedFlight, $lockedUser);

            if (!(bool) setting('bids.allow_multiple_bids', false)
                && Bid::query()->where('user_id', $lockedUser->id)->exists()) {
                throw new UserBidLimit($lockedUser);
            }

            if ((bool) setting('bids.disable_flight_on_bid', false)
                && Bid::query()->where('flight_id', $lockedFlight->id)->exists()) {
                throw new BidExistsForFlight($lockedFlight);
            }

            if ((bool) setting('operations.block_aircraft', false) && $lockedAircraft === null) {
                throw ValidationException::withMessages([
                    'aircraftId' => 'Select an eligible aircraft before placing this bid.',
                ]);
            }

            if ($lockedAircraft instanceof Aircraft) {
                $this->bids->assertAircraftMayBeBid($lockedFlight, $lockedUser, $lockedAircraft);
            }

            $created = Bid::query()->create([
                'user_id'     => $lockedUser->id,
                'flight_id'   => $lockedFlight->id,
                'aircraft_id' => $lockedAircraft?->id,
            ]);

            $this->bids->recomputeFlightBidState($lockedFlight);

            return $created;
        }, 3);

        $flight->refresh();

        return $this->bids->getBid($user, $bid->id) ?? $bid;
    }
}
