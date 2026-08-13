<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
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
        Bid::query()
            ->where('created_at', '<', $date)
            ->pluck('id')
            ->each(fn (int $bidId) => $this->bidSvc->removeBidById($bidId));
    }
}
