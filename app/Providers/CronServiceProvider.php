<?php

namespace App\Providers;

use App\Cron\Hourly\ClearExpiredInvites;
use App\Cron\Hourly\ClearExpiredSimbrief;
use App\Cron\Hourly\DeletePireps;
use App\Cron\Hourly\RemoveExpiredBids;
use App\Cron\Hourly\RemoveExpiredLiveFlights;
use App\Cron\Nightly\ApplyExpenses;
use App\Cron\Nightly\NewVersionCheck;
use App\Cron\Nightly\PilotLeave;
use App\Cron\Nightly\RecalculateBalances;
use App\Cron\Nightly\RecalculateStats;
use App\Cron\Nightly\RefreshOAuthTokens;
use App\Cron\Nightly\SetActiveFlights;
use App\Cron\Weekly\UpdateSimbriefData;
use App\Events\CronFifteenMinute;
use App\Events\CronFiveMinute;
use App\Events\CronHourly;
use App\Events\CronMonthly;
use App\Events\CronNightly;
use App\Events\CronThirtyMinute;
use App\Events\CronWeekly;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * All of the hooks for the cron jobs
 */
class CronServiceProvider extends ServiceProvider
{
    protected $listen = [
        CronFiveMinute::class    => [],
        CronFifteenMinute::class => [],
        CronThirtyMinute::class  => [],
        CronHourly::class        => [
            DeletePireps::class,
            RemoveExpiredBids::class,
            RemoveExpiredLiveFlights::class,
            ClearExpiredSimbrief::class,
            ClearExpiredInvites::class,
        ],
        CronNightly::class => [
            ApplyExpenses::class,
            RecalculateBalances::class,
            PilotLeave::class,
            SetActiveFlights::class,
            RecalculateStats::class,
            NewVersionCheck::class,
            RefreshOAuthTokens::class,
        ],
        CronWeekly::class => [
            UpdateSimbriefData::class,
        ],
        CronMonthly::class => [
            \App\Cron\Monthly\ApplyExpenses::class,
        ],
    ];
}
