<?php

declare(strict_types=1);

use App\Events\CronFifteenMinute;
use App\Events\CronFiveMinute;
use App\Events\CronHourly;
use App\Events\CronMonthly;
use App\Events\CronNightly;
use App\Events\CronThirtyMinute;
use App\Events\CronWeekly;
use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Support\PauseScheduleDuringSetup;
use App\Services\CronService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Schedule;

// Registered first so a failing filter below cannot starve them.
Schedule::call(static function (): void {
    // Update the last time the cron was run
    app(CronService::class)->updateLastRunTime();
})
    ->name('update-last-run-time')
    ->everyMinute()
    ->evenInMaintenanceMode();

// No withoutOverlapping(): its cache mutex needs cache_locks (absent on v7)
// and a killed process leaves it held for a day. The run's flock serialises.
// Not from the web cron: a web request's time limit would stop a migration halfway.
Schedule::call(static fn () => app(RunSetup::class)->handle())
    ->name('setup-run')
    ->skip(static fn (): bool => (bool) Context::getHidden(RunSetup::WEB_CRON))
    ->everyMinute()
    ->evenInMaintenanceMode();

// Resolved at tick time, not at route load.
$panelNotBlocked = static function (): bool {
    // A throwing filter would abort the whole schedule:run; when in doubt, skip.
    try {
        return !app(DetectPendingWork::class)->handle()->blocksCron(PauseScheduleDuringSetup::failedRunTakenOver());
    } catch (Throwable) {
        return false;
    }
};

Schedule::call(static function (): void {
    event(new CronFiveMinute());
})
    ->name('cron-five-minutes')
    ->when($panelNotBlocked)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::call(static function (): void {
    event(new CronFifteenMinute());
})
    ->name('cron-fifteen-minutes')
    ->when($panelNotBlocked)
    ->everyFifteenMinutes()
    ->withoutOverlapping(15);

Schedule::call(static function (): void {
    event(new CronThirtyMinute());
})
    ->name('cron-thirty-minutes')
    ->when($panelNotBlocked)
    ->everyThirtyMinutes()
    ->withoutOverlapping(30);

Schedule::call(static function (): void {
    event(new CronHourly());
})
    ->name('cron-hourly')
    ->when($panelNotBlocked)
    ->hourly()
    ->withoutOverlapping(55);

Schedule::call(static function (): void {
    event(new CronNightly());
})
    ->name('cron-nightly')
    ->when($panelNotBlocked)
    ->dailyAt('01:00')
    ->withoutOverlapping(120);

Schedule::call(static function (): void {
    event(new CronWeekly());
})
    ->name('cron-weekly')
    ->when($panelNotBlocked)
    ->weekly()
    ->withoutOverlapping(120);

Schedule::call(static function (): void {
    event(new CronMonthly());
})
    ->name('cron-monthly')
    ->when($panelNotBlocked)
    ->monthly()
    ->withoutOverlapping(120);

if (config('backup.backup.enabled', false)) {
    Schedule::command('backup:run')->dailyAt('01:15');
    Schedule::command('backup:clean')->dailyAt('01:30');
    Schedule::command('backup:monitor')->dailyAt('01:45');
}

if (config('activitylog.enabled', false)) {
    Schedule::command('activitylog:clean --force')->dailyAt('01:00');
}

// Run queued jobs via cron for environments without Supervisor/systemd
if (config('phpvms.run_queued_jobs_in_cron', false)) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --sleep=3')
        ->name('queue-worker-cron')
        ->when($panelNotBlocked)
        ->withoutOverlapping()
        ->runInBackground()
        ->everyMinute();
}
