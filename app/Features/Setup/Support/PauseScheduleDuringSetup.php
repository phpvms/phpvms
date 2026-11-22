<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;
use App\Services\KvpService;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Throwable;
use WeakMap;

/**
 * Holds every scheduled task, add-on ones included, while an update is live, and for the rest of
 * a tick an update ran in: the tasks it held up must not fire the moment it ends.
 */
final class PauseScheduleDuringSetup
{
    // These keep running: the heartbeat lets Start through, setup-run is the update.
    private const array RUNS_DURING_SETUP = ['update-last-run-time', 'setup-run'];

    // When the pause began, for the admin warning; empty while tasks run.
    public const string PAUSED_SINCE = 'schedule_paused_since';

    private static ?Carbon $tickStartedAt = null;

    /** @var WeakMap<ScheduledEvent, true>|null */
    private static ?WeakMap $gated = null;

    public static function register(): void
    {
        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            if ($event->command !== 'schedule:run') {
                return;
            }

            self::$tickStartedAt = Carbon::now()->startOfSecond();
            self::$gated ??= new WeakMap();

            foreach (app(Schedule::class)->events() as $scheduled) {
                if (isset(self::$gated[$scheduled]) || in_array($scheduled->description, self::RUNS_DURING_SETUP, true)) {
                    continue;
                }

                self::$gated[$scheduled] = true;
                $scheduled->skip(static fn (): bool => self::paused());
            }

            self::recordPause();
        });
    }

    /**
     * When the schedule has been paused since, or null while it runs.
     */
    public static function pausedSince(): ?Carbon
    {
        try {
            $since = app(KvpService::class)->get(self::PAUSED_SINCE);

            return is_string($since) && $since !== '' ? Carbon::parse($since) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A site brought back up by hand after a failure has been taken over; its tasks run again.
     */
    public static function failedRunTakenOver(?RunSnapshot $run = null): bool
    {
        try {
            $run ??= app(SetupRunState::class)->read();
        } catch (Throwable) {
            return false;
        }

        return $run?->status === RunStatus::Failed && !app()->isDownForMaintenance();
    }

    private static function recordPause(): void
    {
        try {
            $kvp = app(KvpService::class);
            $since = $kvp->get(self::PAUSED_SINCE);

            if (self::paused()) {
                if (!is_string($since) || $since === '') {
                    $kvp->save(self::PAUSED_SINCE, Carbon::now()->toIso8601String());
                }
            } elseif (is_string($since) && $since !== '') {
                $kvp->save(self::PAUSED_SINCE, '');
            }
        } catch (Throwable) {
            // Tracking only feeds a warning; it must never stop the tick.
        }
    }

    private static function paused(): bool
    {
        // A throwing filter would abort the whole schedule:run; when in doubt, skip.
        try {
            $run = app(SetupRunState::class)->read();
        } catch (Throwable) {
            return true;
        }

        if ($run === null) {
            return false;
        }

        if (in_array($run->status, [RunStatus::Requested, RunStatus::Running], true)) {
            return true;
        }

        if ($run->status === RunStatus::Failed) {
            return !self::failedRunTakenOver($run);
        }

        return $run->finishedAt !== null
            && self::$tickStartedAt instanceof Carbon
            && Carbon::parse($run->finishedAt)->gte(self::$tickStartedAt);
    }
}
