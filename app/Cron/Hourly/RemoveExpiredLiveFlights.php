<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
use App\Events\PirepCancelled;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Remove expired live flights.
 */
class RemoveExpiredLiveFlights extends Listener
{
    /**
     * Remove expired live flights that haven't had an update in the live time.
     *
     *
     * @throws \Exception
     */
    public function handle(CronHourly $event): void
    {
        if (setting('acars.live_time') === 0) {
            return;
        }

        $date = Carbon::now('UTC')->subHours(setting('acars.live_time'));
        $pireps = Pirep::where('updated_at', '<', $date)
            ->where('state', PirepState::IN_PROGRESS)
            ->where('status', '<>', PirepStatus::PAUSED)
            ->get();

        foreach ($pireps as $pirep) {
            event(new PirepCancelled($pirep));
            Log::info('Cron: Deleting Expired Live PIREP id='.$pirep->id.', state='.PirepState::label($pirep->state));
            $pirep->delete();
        }
    }
}
