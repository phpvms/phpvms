<?php

namespace App\Console;

use App\Console\Cron\Backups\BackupClean;
use App\Console\Cron\Backups\BackupMonitor;
use App\Console\Cron\Backups\BackupRun;
use App\Console\Cron\FifteenMinute;
use App\Console\Cron\FiveMinute;
use App\Console\Cron\Hourly;
use App\Console\Cron\JobQueue;
use App\Console\Cron\Monthly;
use App\Console\Cron\Nightly;
use App\Console\Cron\ThirtyMinute;
use App\Console\Cron\Weekly;
use App\Services\CronService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule. How this works... according to the command
     * time, an event gets send out with the appropriate time (e.g, hourly sends an hourly event)
     *
     * Then the CronServiceProvider has the list of cronjobs which then run according to the events
     * and then calls those at the proper times.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // If not using the queue worker then run those via cron
        if (!config('queue.worker', false)) {
            $schedule->command(JobQueue::class)
                ->everyMinute()
                ->withoutOverlapping();
        }

        // Laravel 10 redis cache
        $schedule->command('cache:prune-stale-tags')->hourly();

        /*
         * NOTE: IF MORE TASKS ARE ADDED, THEY ALSO MUST BE ADDED TO THE CRON.PHP
         */
        $schedule->command(FiveMinute::class)->everyFiveMinutes();
        $schedule->command(FifteenMinute::class)->everyFifteenMinutes();
        $schedule->command(ThirtyMinute::class)->everyThirtyMinutes();
        $schedule->command(Nightly::class)->dailyAt('01:00');
        $schedule->command(Hourly::class)->hourly();
        $schedule->command(Weekly::class)->weeklyOn(0);
        $schedule->command(Monthly::class)->monthlyOn(1);

        // When spatie-backups runs
        if (config('backup.backup.enabled', false) === true) {
            $schedule->command(BackupRun::class)->dailyAt('01:15');
            $schedule->command(BackupClean::class)->dailyAt('01:30');
            $schedule->command(BackupMonitor::class)->dailyAt('01:45');
        }

        // Update the last time the cron was run
        /** @var CronService $cronSvc */
        $cronSvc = app(CronService::class);
        $cronSvc->updateLastRunTime();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        $this->load(__DIR__.'/Cron');
    }
}
