<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
use App\Features\Tour\Models\UserTour;
use App\Features\Tour\TourService;
use App\Models\Bid;
use App\Services\BidService;
use Carbon\Carbon;
use Exception;

/**
 * Remove expired bids
 */
class RemoveExpiredBids extends Listener
{
    public function __construct(
        private readonly BidService $bidSvc,
        private readonly TourService $tourSvc,
    ) {}

    /**
     * Remove expired bids
     *
     *
     * @throws Exception
     */
    public function handle(CronHourly $event): void
    {
        $expireHours = max(0, (int) setting('bids.expire_time', 0));
        if ($expireHours === 0) {
            return;
        }

        $date = Carbon::now('UTC')->subHours($expireHours);
        $expired = Bid::query()->where('created_at', '<', $date)->get(['id', 'user_tour_id']);

        // A tour goes all at once, or a multi-day run would lose its downstream
        // legs one at a time while the pilot is still flying it. expire() drops
        // the tour's remaining bids itself, so those ids are not visited again.
        $expired->pluck('user_tour_id')
            ->filter()
            ->unique()
            ->each(function (string $tourId): void {
                $tour = UserTour::query()->find($tourId);
                if ($tour instanceof UserTour) {
                    $this->tourSvc->expire($tour);
                }
            });

        $expired->whereNull('user_tour_id')
            ->pluck('id')
            ->each(fn (int $bidId) => $this->bidSvc->removeBidById($bidId));
    }
}
