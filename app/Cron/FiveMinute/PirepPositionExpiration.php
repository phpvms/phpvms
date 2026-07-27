<?php

namespace App\Cron\FiveMinute;

use App\Contracts\Listener;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Events\CronFiveMinute;
use App\Models\PirepPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decide what leaves the live map.
 *
 * A row in `pirep_positions` *is* map membership, so the read path does no
 * filtering at all and this listener is the only thing — apart from cancelling a
 * PIREP, and cascading its deletion — that removes a row. The trade named in the
 * design: a bug here leaks flights onto the map rather than producing a slow
 * query, which is the more visible of the two failure modes and therefore the
 * one worth having.
 *
 * Five-minutely rather than hourly, because two of its three timers are
 * expressed in minutes and an hourly job would overshoot a thirty-minute setting
 * by up to fifty-nine. `CronFiveMinute` is already dispatched by
 * `routes/console.php` and `bootstrap/app.php` auto-discovers anything under
 * `app/Cron`, so this directory needs no registration.
 *
 * The three rules read PIREP state from `pireps`, never from the position row's
 * reported phase: phase is client telemetry describing what the aircraft is
 * doing, state is server-owned and describes what the record is, and the two are
 * allowed to disagree without either being wrong.
 */
class PirepPositionExpiration extends Listener
{
    public function handle(CronFiveMinute $event): void
    {
        $now = Carbon::now('UTC');

        $liveTime = (int) setting('livemap.live_time');
        $idleTime = (int) setting('livemap.idle_time');
        $tombstone = (int) setting('pireps.tombstone_time');

        // Zero disables a timer rather than expiring everything instantly.
        if ($liveTime > 0) {
            $this->evict(
                'completed',
                $now->copy()->subMinutes($liveTime),
                // Anything that has left IN_PROGRESS is finished as far as the
                // map is concerned, whatever phase the client last reported —
                // this is the "phase and state disagree" case, and state wins.
                // A soft-deleted PIREP is included: it is off the map already,
                // and without this its row would have nothing left to evict it.
                fn ($query) => $query->where(function ($q): void {
                    $q->where('pireps.state', '<>', PirepState::IN_PROGRESS->value)
                        ->orWhereNotNull('pireps.deleted_at');
                })
            );
        }

        if ($idleTime > 0) {
            $this->evict(
                'stationary',
                $now->copy()->subMinutes($idleTime),
                // One timer for every flight that is not moving. A paused flight
                // and a prefiled one that never departed are the same situation
                // — present, stationary, cluttering the map — so they share a
                // setting rather than needing a fourth.
                //
                // "Never departed" is `updated_at` still equal to `created_at`:
                // only a position batch moves `updated_at`, so a row untouched
                // since prefile opened it has reported nothing of its own.
                fn ($query) => $query->where('pireps.state', PirepState::IN_PROGRESS->value)
                    ->whereNull('pireps.deleted_at')
                    ->where(function ($q): void {
                        $q->where('pireps.status', PirepPhase::PAUSED->value)
                            ->orWhereColumn('pirep_positions.updated_at', '=', 'pirep_positions.created_at');
                    })
            );
        }

        if ($tombstone > 0) {
            $this->evict(
                'silent',
                $now->copy()->subHours($tombstone),
                // A flight that was moving and stopped reporting. Hours, not
                // minutes: this is `acars.live_time` renamed, and reinterpreting
                // an operator's stored number would be worse than the coarser
                // unit.
                fn ($query) => $query->where('pireps.state', PirepState::IN_PROGRESS->value)
                    ->whereNull('pireps.deleted_at')
                    ->where('pireps.status', '<>', PirepPhase::PAUSED->value)
                    ->whereColumn('pirep_positions.updated_at', '<>', 'pirep_positions.created_at')
            );
        }
    }

    /**
     * Remove the position rows matching one rule, clocked from
     * `pirep_positions`.`updated_at`.
     *
     * `updated_at` and not `pireps`.`submitted_at`: the join makes the latter
     * free, but it measures the wrong thing. A pilot who lands at 12:00 and
     * files at 15:00 has an aircraft that stopped existing at 12:00, and
     * clocking from the filing time would leave a landed aeroplane drawn for
     * three and a half hours. `updated_at` stops at the last ping, which is
     * where the aircraft actually stopped.
     *
     * A left join, so a position row whose PIREP has somehow gone is still
     * reachable rather than being silently exempt from every rule. The cost of
     * the join does not matter — this is a scheduled job, and the hot path does
     * not need `pireps`.`state` at all.
     */
    private function evict(string $rule, Carbon $before, callable $constrain): void
    {
        $query = DB::table('pirep_positions')
            ->leftJoin('pireps', 'pireps.id', '=', 'pirep_positions.pirep_id')
            ->where('pirep_positions.updated_at', '<', $before);

        $constrain($query);

        $ids = $query->pluck('pirep_positions.pirep_id');

        if ($ids->isEmpty()) {
            return;
        }

        PirepPosition::whereIn('pirep_id', $ids)->delete();

        Log::info('Cron: removed '.$ids->count().' '.$rule.' flight(s) from the live map');
    }
}
