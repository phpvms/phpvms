<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
use App\Models\Bid;
use Carbon\Carbon;

/**
 * Remove expired bids.
 */
class RemoveExpiredBids extends Listener
{
    /**
     * Remove expired bids.
     *
     *
     * @throws \Exception
     */
    public function handle(CronHourly $event): void
    {
        if (setting('bids.expire_time') === 0) {
            return;
        }

        $date = Carbon::now('UTC')->subHours(setting('bids.expire_time'));
        Bid::where('created_at', '<', $date)->delete();
    }
}
