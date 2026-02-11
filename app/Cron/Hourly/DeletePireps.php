<?php

namespace App\Cron\Hourly;

use App\Contracts\Listener;
use App\Events\CronHourly;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use App\Services\PirepService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Remove cancelled/deleted PIREPs. Look for PIREPs that were created before the setting time
 * (e.g, 12 hours ago) and are marked with the
 */
class DeletePireps extends Listener
{
    /**
     * Delete old rejected PIREPs
     *
     *
     * @throws Exception
     */
    public function handle(CronHourly $event): void
    {
        $this->deletePireps(setting('pireps.delete_rejected_hours'), PirepState::REJECTED);
        $this->deletePireps(setting('pireps.delete_cancelled_hours'), PirepState::CANCELLED);
    }

    /**
     * Look for and delete PIREPs which match the criteria
     *
     * @param int $expire_time_hours The time in hours to look for PIREPs
     * @param int $state             The PirepState enum value
     */
    protected function deletePireps(int $expire_time_hours, int $state)
    {
        $dt = Carbon::now('UTC')->subHours($expire_time_hours);
        $pireps = Pirep::where('created_at', '<', $dt)
            ->where(['state' => $state])
            ->where('status', '<>', PirepStatus::PAUSED)
            ->get();

        /** @var PirepService $pirepSvc */
        $pirepSvc = app(PirepService::class);

        /** @var Pirep $pirep */
        foreach ($pireps as $pirep) {
            Log::info('Cron: Deleting PIREP id='.$pirep->id.', state='.PirepState::label($state));
            $pirepSvc->delete($pirep);
        }
    }
}
