<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Enums\AwardTrigger;
use App\Events\ProcessAward;
use App\Models\Award;
use App\Models\User;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\CriteriaCompilationFailed;
use Illuminate\Support\Facades\Log;

/**
 * Look for and run any of the award classes. Don't modify this.
 * See the documentation on creating awards:
 *
 * @url http://docs.phpvms.net/customizing/awards
 */
class AwardListener extends Listener // implements ShouldQueue
{
    // use Queueable;

    public function __construct(protected AwardRunService $runner) {}

    /**
     * Check for any awards to be run and test them against the user
     */
    public function handle(ProcessAward $event): void
    {
        /** @var Award[] $awards */
        $awards = Award::where('active', 1)->whereDoesntHave('rule')->get();
        foreach ($awards as $award) {
            /** @var ?\App\Contracts\Award $klass */
            $klass = $award->getReference($award, $event->user);
            $klass?->handle();
        }

        $this->runRulesAwards($event->user);
    }

    /**
     * Rules-based awards triggered by a PIREP: the same compiled query the
     * nightly sweep runs, narrowed to the one user whose PIREP was accepted.
     *
     * `last_pirep_id` is the PIREP being processed -- `PirepService::accept()`
     * sets it immediately before firing this event, which is the same handle
     * the legacy `FlightRouteAwards` class takes. Rules scoped to the
     * triggering PIREP narrow their subquery to it.
     */
    private function runRulesAwards(User $user): void
    {
        foreach (AwardRunService::awardsFor(AwardTrigger::Pirep) as $award) {
            try {
                $this->runner->run($award, grant: true, user: $user, triggeringPirepId: $user->last_pirep_id);
            } catch (CriteriaCompilationFailed $e) {
                // Fail closed and keep going: this award grants nobody, the
                // rest of them still get their turn.
                Log::error('Award "'.$award->name.'" was skipped: '.$e->getMessage());
            }
        }
    }
}
