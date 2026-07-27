<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Enums\PirepPhase;
use App\Events\CronHourly;
use App\Events\PirepCancelled;
use App\Models\Pirep;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Remove expired live flights
 */
class RemoveExpiredLiveFlights extends Listener
{
    /**
     * Cancel in-progress PIREPs that have gone silent for longer than the
     * tombstone period.
     *
     * `pireps.tombstone_time` is `acars.live_time` renamed, in the same unit
     * with the same default — this listener's half of the two jobs that setting
     * used to do. The other half, how long a finished flight stays drawn, is now
     * `livemap.live_time` and belongs to PirepPositionExpiration.
     *
     * The PAUSED exemption stays. A paused PIREP is paused deliberately and
     * reaping it would destroy a flight the pilot means to resume; `livemap.idle_time`
     * takes it off the map without touching the record.
     *
     * @throws Exception
     */
    public function handle(CronHourly $event): void
    {
        if (setting('pireps.tombstone_time') === 0) {
            return;
        }

        $pireps = Pirep::silentInProgress(setting('pireps.tombstone_time'))
            ->where('status', '<>', PirepPhase::PAUSED)
            ->get();

        foreach ($pireps as $pirep) {
            event(new PirepCancelled($pirep));
            Log::info('Cron: Deleting Expired Live PIREP id='.$pirep->id.', state='.$pirep->state->getLabel());
            $pirep->delete();
        }
    }
}
