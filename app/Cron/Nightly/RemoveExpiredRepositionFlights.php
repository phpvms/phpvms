<?php

declare(strict_types=1);

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Services\FlightService;

class RemoveExpiredRepositionFlights extends Listener
{
    public function handle(CronNightly $event): void
    {
        app(FlightService::class)->removeExpiredRepositionFlights();
    }
}
