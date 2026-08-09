<?php

declare(strict_types=1);

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Enums\AwardTrigger;
use App\Events\CronNightly;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\CriteriaCompilationFailed;
use Illuminate\Support\Facades\Log;

/**
 * Nightly sweep for trigger=user rules-based awards — time-based criteria
 * that no PIREP event would ever fire, e.g. "member for 12 months".
 *
 * One compiled query per award over the active users who don't already hold
 * it, never one query per user. Legacy class-based awards are unaffected;
 * they run from AwardListener.
 */
class ProcessUserAwards extends Listener
{
    public function __construct(protected AwardRunService $runner) {}

    public function handle(CronNightly $event): void
    {
        foreach (AwardRunService::awardsFor(AwardTrigger::User) as $award) {
            try {
                $this->runner->run($award, grant: true);
            } catch (CriteriaCompilationFailed $e) {
                // One award with an uncompilable tree grants nobody; it must
                // not take the rest of the sweep down with it.
                Log::error('Award "'.$award->name.'" was skipped: '.$e->getMessage());
            }
        }
    }
}
