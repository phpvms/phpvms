<?php

declare(strict_types=1);

use App\Events\CronFifteenMinute;
use App\Events\CronFiveMinute;
use App\Events\CronHourly;
use App\Events\CronMonthly;
use App\Events\CronNightly;
use App\Events\CronThirtyMinute;
use App\Events\CronWeekly;
use App\Services\CronService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(static function (): void {
    event(new CronFiveMinute());
})
    ->name('cron-five-minutes')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::call(static function (): void {
    event(new CronFifteenMinute());
})
    ->name('cron-fifteen-minutes')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15);

Schedule::call(static function (): void {
    event(new CronThirtyMinute());
})
    ->name('cron-thirty-minutes')
    ->everyThirtyMinutes()
    ->withoutOverlapping(30);

Schedule::call(static function (): void {
    event(new CronHourly());
})
    ->name('cron-hourly')
    ->hourly()
    ->withoutOverlapping(55);

Schedule::call(static function (): void {
    event(new CronNightly());
})
    ->name('cron-nightly')
    ->dailyAt('01:00')
    ->withoutOverlapping(120);

Schedule::call(static function (): void {
    event(new CronWeekly());
})
    ->name('cron-weekly')
    ->weekly()
    ->withoutOverlapping(120);

Schedule::call(static function (): void {
    event(new CronMonthly());
})
    ->name('cron-monthly')
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

Schedule::call(static function (): void {
    // Update the last time the cron was run
    app(CronService::class)->updateLastRunTime();
})
    ->name('update-last-run-time')
    ->everyMinute();

// Run queued jobs via cron for environments without Supervisor/systemd
if (config('phpvms.run_queued_jobs_in_cron', false)) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --sleep=3')
        ->name('queue-worker-cron')
        ->withoutOverlapping()
        ->runInBackground()
        ->everyMinute();
}
