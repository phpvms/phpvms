<?php

namespace App\Cron\FiveMinute;

use App\Contracts\Listener;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Events\CronFiveMinute;
use App\Models\PirepPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Decides what leaves the live map. Five-minutely, not hourly, because both of its
 * timers are in minutes.
 */
class PirepPositionExpiration extends Listener
{
    public function handle(CronFiveMinute $event): void
    {
        $now = Carbon::now('UTC');

        $liveTime = (int) setting('livemap.live_time');
        $idleTime = (int) setting('livemap.idle_time');

        // Zero disables a timer rather than expiring everything instantly.
        if ($liveTime > 0) {
            // Finished, whatever phase the client last reported. Soft-deleted
            // PIREPs count, since RemoveExpiredLiveFlights leaves them that way.
            $this->evict(
                $now->copy()->subMinutes($liveTime),
                fn ($query) => $query->where(function ($q): void {
                    $q->where('pireps.state', '<>', PirepState::IN_PROGRESS->value)
                        ->orWhereNotNull('pireps.deleted_at');
                })
            );
        }

        if ($idleTime > 0) {
            // Paused, or prefiled and never departed. "Never departed" is
            // updated_at == created_at, since only a batch moves updated_at.
            $this->evict(
                $now->copy()->subMinutes($idleTime),
                fn ($query) => $query->where('pireps.state', PirepState::IN_PROGRESS->value)
                    ->whereNull('pireps.deleted_at')
                    ->where(function ($q): void {
                        $q->where('pireps.status', PirepPhase::PAUSED->value)
                            ->orWhereColumn('pirep_positions.updated_at', '=', 'pirep_positions.created_at');
                    })
            );
        }
    }

    /**
     * Clocked from pirep_positions.updated_at, not pireps.submitted_at - a pilot
     * who lands at 12:00 and files at 15:00 stopped flying at 12:00.
     */
    private function evict(Carbon $before, callable $constrain): void
    {
        $ids = DB::table('pirep_positions')
            ->join('pireps', 'pireps.id', '=', 'pirep_positions.pirep_id')
            ->where('pirep_positions.updated_at', '<', $before)
            ->tap($constrain)
            ->pluck('pirep_positions.pirep_id');

        // Re-checked, not taken on trust: a batch landing between the select and
        // the delete has refreshed updated_at and must survive.
        PirepPosition::whereIn('pirep_id', $ids)
            ->where('updated_at', '<', $before)
            ->delete();
    }
}
