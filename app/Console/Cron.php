<?php

/**
 * This runs any of the cron tasks that are set to run according to the Laravel schedule
 */

namespace App\Console;

use App\Console\Cron\ActivityLogClean;
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
use App\Contracts\Command;

class Cron
{
    /**
     * @var string[] The cron tasks which get called/run
     */
    private array $cronTasks = [
        JobQueue::class,
        FiveMinute::class,
        FifteenMinute::class,
        ThirtyMinute::class,
        Hourly::class,
        Nightly::class,
        Weekly::class,
        Monthly::class,
        BackupRun::class,
        BackupClean::class,
        BackupMonitor::class,
        ActivityLogClean::class,
    ];

    /**
     * @var array Stores the instantiated cron tasks
     */
    private $cronRunners = [];

    public function __construct(private readonly \Illuminate\Console\Scheduling\Schedule $scheduler)
    {
        foreach ($this->cronTasks as $task) {
            /** @var Command $cronTask */
            $cronTask = app($task);
            $signature = $cronTask->getSignature();

            if (empty($signature)) {
                continue;
            }

            $this->cronRunners[$signature] = $cronTask;
        }
    }

    /**
     * Try to figure out which commands are supposed to run right now
     *
     * @return array string of tasks that were run
     */
    public function run(): array
    {
        $events = $this->scheduler->dueEvents(app());
        if (empty($events)) {
            return [];
        }

        $run = [];

        /** @var \Illuminate\Console\Scheduling\Event $event */
        foreach ($events as $event) {
            foreach ($this->cronRunners as $signature => $task) {
                if (!str_contains((string) $event->command, $signature)) {
                    continue;
                }

                $task->callEvent();
                $run[] = $signature;
            }
        }

        return $run;
    }
}
