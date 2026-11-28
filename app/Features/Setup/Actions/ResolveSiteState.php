<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Exceptions\SiteUnavailable;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SiteState;
use App\Features\Setup\Support\CachedPendingWork;
use App\Features\Setup\Support\RunSnapshot;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Models\User;
use App\Support\InstallMarker;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Which of the setup cases a request meets. Never throws: a database error is Unavailable or Install.
 */
final readonly class ResolveSiteState
{
    public function __construct(
        private SetupRunState $runState,
        private UpgradeCompleted $completed,
        private InstallMarker $marker,
        private CachedPendingWork $pending,
    ) {}

    public function handle(): SiteState
    {
        try {
            // An installed, fully upgraded site needs no user or run-state lookups; only pending work can change its state.
            if ($this->marker->isTrusted() && $this->completed->fileExists()) {
                return $this->pending->handle()->blocksPanel() ? SiteState::UpdatePending : SiteState::Current;
            }

            $hasUsers = Schema::hasTable('users') && User::withTrashed()->exists();
            $run = $this->run();
            $completed = $this->completed->exists();

            if (!$hasUsers) {
                // Schema state alone is not enough: later steps can still be unfinished.
                $done = !$run instanceof RunSnapshot || $run->status === RunStatus::Done;

                return $completed && $done ? SiteState::FirstUser : SiteState::Install;
            }

            // A demo install that failed after its seed step has users but is not a v7 site.
            if ($run instanceof RunSnapshot && $run->fresh && $run->status !== RunStatus::Done) {
                return SiteState::Install;
            }

            $blocked = $this->pending->handle()->blocksPanel();

            // Nothing pending and no marker: the upgrader would refuse and never write it, so the site is current.
            if (!$completed) {
                return $blocked ? SiteState::V7Upgrade : SiteState::Current;
            }

            return $blocked ? SiteState::UpdatePending : SiteState::Current;
        } catch (Throwable $throwable) {
            // Not only a lost connection: a bug here would otherwise read as "no database".
            if (!SiteUnavailable::causedBy($throwable)) {
                report($throwable);
            }

            return $this->marker->exists() ? SiteState::Unavailable : SiteState::Install;
        }
    }

    private function run(): ?RunSnapshot
    {
        try {
            return $this->runState->read();
        } catch (Throwable) {
            // An unreadable state file is not a database problem; treat it as no run.
            return null;
        }
    }
}
