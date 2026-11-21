<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;
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
        });
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

        if (in_array($run->status, [RunStatus::Requested, RunStatus::Running, RunStatus::Failed], true)) {
            return true;
        }

        return $run->finishedAt !== null
            && self::$tickStartedAt instanceof Carbon
            && Carbon::parse($run->finishedAt)->gte(self::$tickStartedAt);
    }
}
