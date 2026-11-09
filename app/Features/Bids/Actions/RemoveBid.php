<?php

declare(strict_types=1);

namespace App\Features\Bids\Actions;

use App\Features\Flights\Queries\VisibleFlights;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Features\Tour\TourService;
use App\Models\Bid;
use App\Models\User;
use App\Services\BidService;

/**
 * Remove a pilot's bid on a flight. If the bid is a leg of a tour in progress,
 * the whole tour run is cancelled.
 */
final readonly class RemoveBid
{
    public function __construct(
        private BidService $bids,
        private TourService $tours,
        private VisibleFlights $flights,
    ) {}

    public function handle(User $user, int|string $flightId): void
    {
        // The pilot's own bid decides this, not the leg's visibility. A run
        // deliberately outlives its bundle's window, and SetVisibleFlights
        // turns the remaining legs invisible when that window closes — so
        // resolving the flight through the visible-only query first would 404
        // "Cancel tour" in exactly the state the tour is meant to survive.
        $bid = Bid::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flightId)
            ->first();

        // Dropping a tour leg means the pilot is done with the whole chain —
        // removeBid() itself stays tour-unaware, because filing a leg goes
        // through it too and must not end the run.
        $tour = $bid?->userTour;

        if ($tour instanceof UserTour && $tour->status === TourStatus::InProgress) {
            $this->tours->cancel($tour);

            return;
        }

        $this->bids->removeBid($this->flights->for($user)->findOrFail($flightId), $user);
    }
}
